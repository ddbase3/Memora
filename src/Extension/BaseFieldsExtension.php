<?php declare(strict_types=1);

namespace Memora\Extension;

use Base3\Api\ISortable;
use Memora\Api\IEntryQueryExtension;

class BaseFieldsExtension implements IEntryQueryExtension, ISortable {

	public function isApplicable(array $options): bool {
		// Always applicable, defines the base fields of each entry
		return true;
	}

	public function applyToQuery(array $query, array $options): array {
		// Add base fields (if not already defined)
		$baseFields = [
			[ 'element' => [ 'type' => 'fld', 'table' => 'base3system_sysentry', 'field' => 'id' ], 'alias' => 'id' ],
			[ 'element' => [ 'type' => 'fld', 'table' => 'base3system_sysentry', 'field' => 'uuid' ], 'alias' => 'uuid' ],
			[ 'element' => [ 'type' => 'fld', 'table' => 'base3system_sysentry', 'field' => 'type_id' ], 'alias' => 'type_id' ],
			[ 'element' => [ 'type' => 'fld', 'table' => 'base3system_sysentry', 'field' => 'archive' ], 'alias' => 'archive' ],
			[ 'element' => [ 'type' => 'fld', 'table' => 'base3system_sysentry', 'field' => 'dellock' ], 'alias' => 'dellock' ],
			[ 'element' => [ 'type' => 'fld', 'table' => 'base3system_sysentry', 'field' => 'connections' ], 'alias' => 'connections' ],
			[ 'element' => [ 'type' => 'fld', 'table' => 'base3system_sysentry', 'field' => 'etag' ], 'alias' => 'etag' ],
			[ 'element' => [ 'type' => 'fld', 'table' => 'base3system_sysentry', 'field' => 'created' ], 'alias' => 'created' ],
			[ 'element' => [ 'type' => 'fld', 'table' => 'base3system_sysentry', 'field' => 'changed' ], 'alias' => 'changed' ],
			[ 'element' => [ 'type' => 'fld', 'table' => 'base3system_systype', 'field' => 'alias', 'variant' => 'optional' ], 'alias' => 'type_alias' ]
		];

		// Merge base fields only if not already defined
		if (empty($query['fields'])) {
			$query['fields'] = $baseFields;
		} else {
			$existingAliases = array_column($query['fields'], 'alias');
			foreach ($baseFields as $field) {
				if (!in_array($field['alias'], $existingAliases, true)) {
					$query['fields'][] = $field;
				}
			}
		}

		// Ensure base table is set
		$query['table'] = $query['table'] ?? 'base3system_sysentry';

		return $query;
	}

	public function processResult(array $rows, array $options): array {
		// Convert binary fields (uuid, etag) to readable hex strings
		foreach ($rows as &$row) {
			if (isset($row['uuid']) && !is_null($row['uuid'])) {
				$row['uuid'] = bin2hex($row['uuid']);
			}
			if (isset($row['etag']) && !is_null($row['etag'])) {
				$row['etag'] = bin2hex($row['etag']);
			}
		}
		unset($row);
		return $rows;
	}

	public function getPriority(): int {
		// Should run first, before filters or optional loads
		return 100;
	}
}

