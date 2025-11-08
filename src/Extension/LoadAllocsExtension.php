<?php declare(strict_types=1);

namespace Memora\Extension;

use Base3\Api\ISortable;
use Memora\Api\IMemoraQueryExtension;

class LoadAllocsExtension implements IMemoraQueryExtension, ISortable {

	public function isApplicable(array $options): bool {
		return !empty($options['loadallocs']);
	}

	public function applyToQuery(array $query, array $options): array {
		// Add allocation aggregation field
		$query['fields'][] = [
			'element' => [
				'type' => 'fn',
				'function' => 'GROUP_CONCAT',
				'params' => [
					[
						'type' => 'fld',
						'table' => 'base3system_sysallocview',
						'tablealias' => 'loadallocs',
						'field' => 'peer_id',
						'variant' => 'optional'
					]
				]
			],
			'alias' => 'allocs'
		];

		return $query;
	}

	public function processResult(array $rows, array $options): array {
		// Convert comma-separated peer_ids into arrays of integers
		foreach ($rows as &$row) {
			if (!empty($row['allocs']) && is_string($row['allocs'])) {
				$row['allocs'] = array_values(array_filter(
					array_map('intval', explode(',', $row['allocs']))
				));
			}
		}
		unset($row);

		return $rows;
	}

	public function getPriority(): int {
		// Execute around same time as LoadTagsExtension
		return 840;
	}
}
