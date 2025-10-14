<?php declare(strict_types=1);

namespace Memora\Extension;

use Base3\Api\ISortable;
use Memora\Api\IEntryQueryExtension;

class OrderExtension implements IEntryQueryExtension, ISortable {

	public function isApplicable(array $options): bool {
		// Always applicable unless explicitly disabled
		return true;
	}

	public function applyToQuery(array $query, array $options): array {
		// Apply default or user-defined ordering
		$field = $options['orderfield'] ?? 'id';
		$direction = strtoupper($options['orderdir'] ?? 'DESC');

		$query['order_by'] = [
			[
				'element' => [
					'type' => 'fld',
					'table' => 'base3system_sysentry',
					'field' => $field
				],
				'direction' => in_array($direction, ['ASC', 'DESC'], true) ? $direction : 'DESC'
			]
		];

		return $query;
	}

	public function processResult(array $rows, array $options): array {
		// No postprocessing required for ordering
		return $rows;
	}

	public function getPriority(): int {
		// Execute after filters, before grouping
		return 900;
	}
}
