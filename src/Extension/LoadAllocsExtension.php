<?php declare(strict_types=1);

namespace Memora\Extension;

use Base3\Api\ISortable;
use Memora\Api\IMemoraQueryExtension;

class LoadAllocsExtension implements IMemoraQueryExtension, ISortable {

	public function isApplicable(array $options): bool {
		return !empty($options['loadallocs']);
	}

	public function applyToQuery(array $query, array $options): array {
		// Add allocation aggregation field (DISTINCT prevents duplicates caused by row-multiplication through joins)
		$query['fields'][] = [
			'element' => [
				'type' => 'fn',
				'function' => 'GROUP_CONCAT',
				'params' => [
					[
						'type' => 'fn',
						'function' => 'DISTINCT',
						'params' => [
							[
								'type' => 'fld',
								'table' => 'base3system_sysallocview',
								'tablealias' => 'loadallocs',
								'field' => 'peer_id',
								'variant' => 'optional'
							]
						]
					]
				]
			],
			'alias' => 'allocs'
		];

		return $query;
	}

	public function processResult(array $rows, array $options): array {
		foreach ($rows as &$row) {
			if (!empty($row['allocs']) && is_string($row['allocs'])) {
				$vals = array_filter(array_map('trim', explode(',', $row['allocs'])), fn($v) => $v !== '');
				$ints = array_map('intval', $vals);
				$ints = array_values(array_unique(array_filter($ints, fn($v) => $v > 0)));
				$row['allocs'] = $ints;
			} else {
				$row['allocs'] = $row['allocs'] ?? [];
				if (!is_array($row['allocs'])) {
					$row['allocs'] = [];
				}
			}
		}
		unset($row);

		return $rows;
	}

	public function getPriority(): int {
		return 840;
	}
}
