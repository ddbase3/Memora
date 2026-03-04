<?php declare(strict_types=1);

namespace Memora\Service;

use Base3\Api\IClassMap;
use Memora\Api\IMemoraProfileService;
use Memora\Api\IMemoraQueryExtension;
use ResourceFoundation\Api\IEntityDataService;
use ResourceFoundation\Api\IQueryService;
use ResourceFoundation\Exception\AccessDeniedException;
use ResourceFoundation\Exception\QueryValidationException;

class MemoraEntityDataService implements IEntityDataService {

	public function __construct(
		private readonly IQueryService $dataqueryservice,
		private readonly IClassMap $classmap,
		private readonly IMemoraProfileService $profiles
	) {}

	public function getEntries(array $options = []): array {
		// Apply active user profile to options (if any)
		$options = $this->applyProfileOptions($options);

		// Load all available extensions
		$extensions = $this->classmap->getInstancesByInterface(IMemoraQueryExtension::class);
		usort($extensions, fn($a, $b) => $a->getPriority() <=> $b->getPriority());

		// Initialize minimal base query (extensions define structure)
		$query = [
			'type' => 'select',
			'fields' => [],
			'table' => null,
			'where' => []
		];

		// Apply all applicable extensions to query
		foreach ($extensions as $ext) {
			if ($ext->isApplicable($options)) {
				$query = $ext->applyToQuery($query, $options);
			}
		}

		// Combine where clauses into AND structure if needed
		if (!empty($query['where']) && is_array($query['where'])) {
			if (!isset($query['where']['type']) && count($query['where']) > 1) {
				$query['where'] = [
					'type' => 'op',
					'operator' => 'AND',
					'params' => $query['where']
				];
			} elseif (empty($query['where'])) {
				unset($query['where']);
			}
		}

		// Execute query
		$result = $this->dataqueryservice->executeQuery($query);
		$rows = $result->rows ?? [];

		// Process results via extensions
		foreach ($extensions as $ext) {
			if ($ext->isApplicable($options)) {
				$rows = $ext->processResult($rows, $options);
			}
		}

		return $rows;
	}

	public function getEntry(int|string $id, array $options = []): ?array {
		$options['entry'] = $id;
		$entries = $this->getEntries($options);
		return $entries[0] ?? null;
	}

	public function saveEntry(array $data): int|string {
		// TODO implement
		return 0;
	}

	public function deleteEntry(int|string $id): bool {
		// 1) Read entry with access information (no guessing in DELETE where-joins)
		//    - AccessExtension filters visibility
		//    - LoadAccessExtension calculates access='edit' for admins / owners / moderators
		$entry = $this->getEntry($id, [
			'loadaccess' => true
		]);

		if (!$entry) {
			return false;
		}

		// Enforce delete permission via computed access column
		if (($entry['access'] ?? 'none') !== 'edit') {
			return false;
		}

		// Respect deletion lock if present
		if (!empty($entry['dellock'])) {
			return false;
		}

		// 2) Perform the actual delete (single-table delete, strict where by id)
		$query = [
			'type' => 'delete',
			'table' => 'base3system_sysentry',
			'where' => [
				'type' => 'op',
				'operator' => '=',
				'params' => [
					[
						'type' => 'fld',
						'table' => 'base3system_sysentry',
						'field' => 'id'
					],
					$id
				]
			],
			'limit' => 1
		];

		try {
			$result = $this->dataqueryservice->executeQuery($query);
		} catch (AccessDeniedException|QueryValidationException|\Throwable $e) {
			return false;
		}

		// DefaultReportQueryService encodes DB errors into the sql string
		$sql = $result->sql ?? '';
		if ($sql !== '' && str_contains($sql, '❌ DB Error:')) {
			return false;
		}

		return true;
	}

	private function applyProfileOptions(array $options): array {
		$profile = $this->profiles->getActiveProfile();
		if (!$profile || empty($profile['profile'])) {
			return $options;
		}

		$expr = $profile['profile'];
		preg_match_all('/\[(.*?)\]/', $expr, $matches);

		foreach ($matches[1] as $block) {
			if (!str_contains($block, '=')) continue;
			[$key, $val] = explode('=', $block, 2);
			$key = trim($key);
			$vals = array_map('trim', explode(',', $val));

			switch ($key) {
				case 'excludealloc':
				case 'excludetag':
				case 'tag':
					$options[$key] = array_merge($options[$key] ?? [], $vals);
					break;
				case 'archive':
					$options['archive'] = $val;
					break;
				default:
					// ignore unknown filters
					break;
			}
		}

		return $options;
	}
}
