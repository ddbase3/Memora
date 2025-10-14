<?php declare(strict_types=1);

namespace Memora\Service;

use ResourceFoundation\Api\IEntityDataService;
use DataHawk\Api\IReportQueryService;
use Base3\Api\IClassMap;
use Memora\Api\IEntryQueryExtension;

class MemoraEntityDataService implements IEntityDataService {

	public function __construct(
		private readonly IReportQueryService $dataqueryservice,
		private readonly IClassMap $classmap
	) {}

	public function getEntries(array $options = []): array {
		// Base query structure
		$query = [
			'type' => 'select',
			'fields' => [
				[ 'element' => [ 'type' => 'fld', 'table' => 'base3system_sysentry', 'field' => 'id' ], 'alias' => 'id' ],
				[ 'element' => [ 'type' => 'fld', 'table' => 'base3system_sysentry', 'field' => 'archive' ], 'alias' => 'archive' ],
				[ 'element' => [ 'type' => 'fld', 'table' => 'base3system_sysentry', 'field' => 'dellock' ], 'alias' => 'dellock' ],
				[ 'element' => [ 'type' => 'fld', 'table' => 'base3system_sysentry', 'field' => 'created' ], 'alias' => 'created' ],
				[ 'element' => [ 'type' => 'fld', 'table' => 'base3system_sysentry', 'field' => 'changed' ], 'alias' => 'changed' ]
			],
			'table' => 'base3system_sysentry',
			'where' => []
		];

		// Load all available extensions
		$extensions = $this->classmap->getInstancesByInterface(IEntryQueryExtension::class);
		usort($extensions, fn($a, $b) => $a->getPriority() <=> $b->getPriority());

		// Apply all applicable extensions to query
		foreach ($extensions as $ext) {
			if ($ext->isApplicable($options)) {
				$query = $ext->applyToQuery($query, $options);
			}
		}

		// Combine where clauses into AND structure
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
