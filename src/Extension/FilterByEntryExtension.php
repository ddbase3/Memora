<?php declare(strict_types=1);

namespace Memora\Extension;

use Base3\Api\ISortable;
use Memora\Api\IEntryQueryExtension;

class FilterByEntryExtension implements IEntryQueryExtension, ISortable {

	public function isApplicable(array $options): bool {
		return !empty($options['entry']);
	}

	public function applyToQuery(array $query, array $options): array {
		// Filter by one or more entry IDs
		$ids = is_array($options['entry']) ? $options['entry'] : [$options['entry']];
		$query['where'][] = [
			'type' => 'op',
			'operator' => count($ids) === 1 ? '=' : 'IN',
			'params' => [
				[
					'type' => 'fld',
					'table' => 'base3system_sysentry',
					'field' => 'id'
				],
				count($ids) === 1 ? $ids[0] : $ids
			]
		];
		return $query;
	}

	public function processResult(array $rows, array $options): array {
		// No postprocessing required for entry filter
		return $rows;
	}

	public function getPriority(): int {
		// Execute early, before type and tag filters
		return 100;
	}
}

