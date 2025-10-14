<?php declare(strict_types=1);

namespace Memora\Extension;

use Base3\Api\ISortable;
use Memora\Api\IEntryQueryExtension;
use DataHawk\Api\IReportQueryService;

class LoadDataExtension implements IEntryQueryExtension, ISortable {

	public function __construct(
		private readonly IReportQueryService $dataqueryservice
	) {}

	public function isApplicable(array $options): bool {
		return !empty($options['loaddata']);
	}

	public function applyToQuery(array $query, array $options): array {
		$existingAliases = array_column($query['fields'] ?? [], 'alias');

		// Temporär: type_alias (wird später wieder entfernt)
		if (!in_array('type_alias', $existingAliases, true)) {
			$query['fields'][] = [
				'element' => [ 'type' => 'fld', 'table' => 'base3system_systype', 'field' => 'alias' ],
				'alias' => 'type_alias'
			];
		}

		// Für Payload-Auflösung
		if (!in_array('type_dbtable', $existingAliases, true)) {
			$query['fields'][] = [
				'element' => [ 'type' => 'fld', 'table' => 'base3system_systype', 'field' => 'dbtable' ],
				'alias' => 'type_dbtable'
			];
		}
		if (!in_array('type_primary', $existingAliases, true)) {
			$query['fields'][] = [
				'element' => [ 'type' => 'fld', 'table' => 'base3system_systype', 'field' => 'primary' ],
				'alias' => 'type_primary'
			];
		}

		return $query;
	}

	public function processResult(array $rows, array $options): array {
		if (empty($rows)) {
			return $rows;
		}

		// Gruppierung nach Typ
		$typeGroups = [];
		foreach ($rows as $row) {
			$type = $row['type_alias'] ?? null;
			if ($type) {
				$typeGroups[$type][] = $row;
			}
		}

		// Payload pro Typ laden
		foreach ($typeGroups as $typeAlias => $entries) {
			$dbtable = $entries[0]['type_dbtable'] ?? null;
			$primary = $entries[0]['type_primary'] ?? 'id';

			if (!$dbtable) continue;

			$ids = array_column($entries, 'id');
			if (empty($ids)) continue;

			$payloadQuery = [
				'type' => 'select',
				'fields' => [
					[ 'element' => [ 'type' => 'fld', 'table' => $dbtable, 'field' => '*' ] ]
				],
				'table' => $dbtable,
				'where' => [
					'type' => 'op',
					'operator' => 'IN',
					'params' => [
						[ 'type' => 'fld', 'table' => $dbtable, 'field' => $primary ],
						$ids
					]
				]
			];

			$payloadResult = $this->dataqueryservice->executeQuery($payloadQuery);
			$payloadRows = $payloadResult->rows ?? [];
			if (empty($payloadRows)) continue;

			$payloadById = [];
			foreach ($payloadRows as $p) {
				if (isset($p[$primary])) {
					$payloadById[$p[$primary]] = $p;
				}
			}

			foreach ($rows as &$row) {
				if (($row['type_alias'] ?? null) === $typeAlias && isset($payloadById[$row['id']])) {
					$row['data'] = $payloadById[$row['id']];
				}
			}
			unset($row);
		}

		// Entferne interne Hilfsfelder
		foreach ($rows as &$row) {
			unset($row['type_alias'], $row['type_dbtable'], $row['type_primary']);
		}
		unset($row);

		return $rows;
	}

	public function getPriority(): int {
		return 990;
	}
}

