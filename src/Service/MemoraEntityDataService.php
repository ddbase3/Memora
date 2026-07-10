<?php declare(strict_types=1);

namespace Memora\Service;

use Base3\Api\IClassMap;
use Memora\Api\IMemoraCreateExtension;
use Memora\Api\IMemoraQueryExtension;
use Memora\Api\IMemoraQueryService;
use Memora\Api\IMemoraUpdateExtension;
use ResourceFoundation\Api\IEntityDataService;
use ResourceFoundation\Api\IEntityProfileService;
use ResourceFoundation\Exception\AccessDeniedException;
use ResourceFoundation\Exception\QueryValidationException;

class MemoraEntityDataService implements IEntityDataService {

	public function __construct(
		private readonly IMemoraQueryService $dataqueryservice,
		private readonly IClassMap $classmap,
		private readonly IEntityProfileService $profiles
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
		$patch = $patch['patch'] ?? $patch;

		if (!is_array($patch) || empty($patch)) {
			throw new \InvalidArgumentException("updateEntry expects a non-empty patch array.");
		}

		$currentEntry = $this->getEntry($id, [
			'loadaccess' => true
		]);

		if ($currentEntry === null) {
			throw new \RuntimeException("Entry not found: " . $id);
		}
		if (($currentEntry['access'] ?? 'none') !== 'edit') {
			throw new AccessDeniedException("Update denied for entry " . $id . ".");
		}
		if (!empty($currentEntry['dellock'])) {
			throw new \RuntimeException("Entry " . $id . " is delete-locked and cannot be updated.");
		}

		$extensions = $this->classmap->getInstancesByInterface(IMemoraUpdateExtension::class);
		if (empty($extensions)) {
			throw new \RuntimeException("No IMemoraUpdateExtension implementations registered.");
		}

		usort($extensions, fn($a, $b) => $a->getPriority() <=> $b->getPriority());

		$context = [
			'entry_id' => (int)$id,
			'current_entry' => $currentEntry,
			'transaction_queries' => []
		];

		foreach ($extensions as $ext) {
			if ($ext->isApplicable($patch)) {
				$ext->beforeUpdate($patch, $context);
			}
		}

		foreach ($extensions as $ext) {
			if (!$ext->isApplicable($patch)) continue;
			$ext->update($patch, $context);
		}

		$txQueries = $context['transaction_queries'] ?? [];
		if (!is_array($txQueries) || empty($txQueries)) {
			throw new \InvalidArgumentException("Patch did not contain any supported update operations.");
		}

		$this->dataqueryservice->executeQuery([
			'type' => 'transaction',
			'queries' => $txQueries
		]);

		foreach ($extensions as $ext) {
			if ($ext->isApplicable($patch)) {
				$ext->afterUpdate($patch, $context);
			}
		}

		return $id;
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
		if (!empty($options['ignoreprofile']) || isset($options['entry'])) {
			unset($options['ignoreprofile']);
			return $options;
		}

		$profile = $this->profiles->getActiveProfile();
		if (!$profile || empty($profile['profile'])) {
			return $options;
		}

		$expr = (string)$profile['profile'];
		preg_match_all('/\[(.*?)\]/', $expr, $matches);

		foreach ($matches[1] as $block) {
			if (!str_contains($block, '=')) continue;

			[$key, $val] = explode('=', $block, 2);
			$key = trim($key);
			$val = trim($val);

			switch ($key) {
				case 'excludealloc':
				case 'excludetag':
				case 'tag':
				case 'module':
					$options[$key] = $this->mergeOptionList($options[$key] ?? [], $val);
					break;

				case 'archive':
					if ($val !== '') {
						$options['archive'] = $val;
					}
					break;

				default:
					break;
			}
		}

		return $options;
	}

	private function mergeOptionList(mixed $current, mixed $append): array {
		$values = array_merge(
			$this->normalizeOptionList($current),
			$this->normalizeOptionList($append)
		);

		return array_values(array_unique($values));
	}

	private function normalizeOptionList(mixed $value): array {
		if (is_array($value)) {
			$result = [];

			foreach ($value as $item) {
				foreach ($this->normalizeOptionList($item) as $normalizedItem) {
					$result[] = $normalizedItem;
				}
			}

			return array_values(array_unique($result));
		}

		$string = trim((string)$value);
		if ($string === '') {
			return [];
		}

		$parts = array_map('trim', explode(',', $string));
		$parts = array_filter($parts, static fn(string $part): bool => $part !== '');

		return array_values(array_unique($parts));
	}
}
