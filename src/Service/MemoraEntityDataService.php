<?php declare(strict_types=1);

namespace Memora\Service;

use Base3\Api\IClassMap;
use Memora\Api\IMemoraCreateExtension;
use Memora\Api\IMemoraProfileService;
use Memora\Api\IMemoraQueryExtension;
use Memora\Api\IMemoraQueryService;
use ResourceFoundation\Api\IEntityDataService;
use ResourceFoundation\Exception\AccessDeniedException;
use ResourceFoundation\Exception\QueryValidationException;

class MemoraEntityDataService implements IEntityDataService {

	public function __construct(
		private readonly IMemoraQueryService $dataqueryservice,
		private readonly IClassMap $classmap,
		private readonly IMemoraProfileService $profiles
	) {}

	public function getEntries(array $options = []): array {
		$options = $this->applyProfileOptions($options);

		$extensions = $this->classmap->getInstancesByInterface(IMemoraQueryExtension::class);
		usort($extensions, fn($a, $b) => $a->getPriority() <=> $b->getPriority());

		$query = [
			'type' => 'select',
			'fields' => [],
			'table' => null,
			'where' => []
		];

		foreach ($extensions as $ext) {
			if ($ext->isApplicable($options)) {
				$query = $ext->applyToQuery($query, $options);
			}
		}

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

		$result = $this->dataqueryservice->executeQuery($query);
		$rows = $result->rows ?? [];

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

	public function createEntry(array $data): int|string {
		$entry = $data['entry'] ?? $data;

		if (!is_array($entry) || empty($entry)) {
			throw new \InvalidArgumentException("createEntry expects an 'entry' array payload.");
		}

		$extensions = $this->classmap->getInstancesByInterface(IMemoraCreateExtension::class);
		if (empty($extensions)) {
			throw new \RuntimeException("No IMemoraCreateExtension implementations registered.");
		}

		usort($extensions, fn($a, $b) => $a->getPriority() <=> $b->getPriority());

		$context = [
			'entry_id' => null,
			'type_id' => null,
			'type_alias' => null,
			'type_dbtable' => null,
			'type_primary' => null,
			'transaction_queries' => []
		];

		foreach ($extensions as $ext) {
			if ($ext->isApplicable($entry)) {
				$ext->beforeCreate($entry, $context);
			}
		}

		foreach ($extensions as $ext) {
			if (!$ext->isApplicable($entry)) continue;
			$ext->create($entry, $context);
		}

		if (empty($context['entry_id'])) {
			throw new \RuntimeException("Create pipeline finished without setting context['entry_id'].");
		}

		$txQueries = $context['transaction_queries'] ?? [];
		if (is_array($txQueries) && count($txQueries)) {
			try {
				$this->dataqueryservice->executeQuery([
					'type' => 'transaction',
					'queries' => $txQueries
				]);
			} catch (\Throwable $e) {
				try {
					$this->dataqueryservice->executeQuery([
						'type' => 'delete',
						'table' => 'base3system_sysentry',
						'where' => [
							'type' => 'op',
							'operator' => '=',
							'params' => [
								[ 'type' => 'fld', 'table' => 'base3system_sysentry', 'field' => 'id' ],
								$context['entry_id']
							]
						],
						'limit' => 1
					]);
				} catch (\Throwable $cleanupError) {
				}

				throw $e;
			}
		}

		foreach ($extensions as $ext) {
			if ($ext->isApplicable($entry)) {
				$ext->afterCreate($entry, $context);
			}
		}

		return $context['entry_id'];
	}

	public function updateEntry(int|string $id, array $patch): int|string {
		return 0;
	}

	public function deleteEntry(int|string $id): bool {
		$entry = $this->getEntry($id, [
			'loadaccess' => true
		]);

		if (!$entry) return false;
		if (($entry['access'] ?? 'none') !== 'edit') return false;
		if (!empty($entry['dellock'])) return false;

		$query = [
			'type' => 'delete',
			'table' => 'base3system_sysentry',
			'where' => [
				'type' => 'op',
				'operator' => '=',
				'params' => [
					[ 'type' => 'fld', 'table' => 'base3system_sysentry', 'field' => 'id' ],
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

		$sql = $result->debugSql ?? '';
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
					break;
			}
		}

		return $options;
	}
}
