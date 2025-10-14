<?php declare(strict_types=1);

namespace Memora\Extension;

use Base3\Api\ISortable;
use Memora\Api\IEntryQueryExtension;

class FilterByTypeExtension implements IEntryQueryExtension, ISortable {

	// Implementation of IEntryQueryExtension

	public function isApplicable(array $options): bool {
		return !empty($options['type']);
	}

	public function applyToQuery(array $query, array $options): array {
		// Add filter by type alias (supports single or multiple types)
		$types = is_array($options['type']) ? $options['type'] : [$options['type']];
		$query['where'][] = [
			'type' => 'op',
			'operator' => count($types) === 1 ? '=' : 'IN',
			'params' => [
				[
					'type' => 'fld',
					'table' => 'base3system_systype',
					'field' => 'alias',
					'variant' => 'required'
				],
				count($types) === 1 ? $types[0] : $types
			]
		];
		return $query;
	}

	public function processResult(array $rows, array $options): array {
		// No postprocessing required for type filter
		return $rows;
	}
	
	// Implementation of ISortable

	public function getPriority(): int {
		return 110;
	}
}
