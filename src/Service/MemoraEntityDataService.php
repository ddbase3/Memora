<?php declare(strict_types=1);

namespace Memora\Service;

use Base3\Api\IClassMap;
use Memora\Api\IMemoraQueryExtension;
use ResourceFoundation\Api\IEntityDataService;
use ResourceFoundation\Api\IQueryService;

class MemoraEntityDataService implements IEntityDataService {

	public function __construct(
		private readonly IQueryService $dataqueryservice,
		private readonly IClassMap $classmap
	) {}

	public function getEntries(array $options = []): array {
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
		// TODO implement
		return false;
	}
}

