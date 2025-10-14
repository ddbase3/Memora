<?php declare(strict_types=1);

namespace Memora\Extension;

use Base3\Api\ISortable;
use Memora\Api\IEntryQueryExtension;

class LoadTypeExtension implements IEntryQueryExtension, ISortable {

	public function isApplicable(array $options): bool {
		return !empty($options['loadtype']);
	}

	public function applyToQuery(array $query, array $options): array {
		// Add field for type alias (as "type")
		$query['fields'][] = [
			'element' => [
				'type' => 'fld',
				'table' => 'base3system_systype',
				'field' => 'alias'
			],
			'alias' => 'type'
		];

		return $query;
	}

	public function processResult(array $rows, array $options): array {
		// No transformation needed; "type" already present in rows
		return $rows;
	}

	public function getPriority(): int {
		// Execute after name and tag loads, before grouping
		return 800;
	}
}
