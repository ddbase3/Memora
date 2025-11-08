<?php declare(strict_types=1);

namespace Memora\Extension;

use Base3\Api\ISortable;
use Memora\Api\IMemoraQueryExtension;

class FilterByAllocExtension implements IMemoraQueryExtension, ISortable {

	public function isApplicable(array $options): bool {
		return !empty($options['alloc'])
			|| !empty($options['inalloc'])
			|| !empty($options['excludealloc']);
	}

	public function applyToQuery(array $query, array $options): array {

		// --- alloc: AND filter (multiple entries all required) ---
		if (!empty($options['alloc'])) {
			$peers = is_array($options['alloc']) ? $options['alloc'] : [$options['alloc']];
			foreach ($peers as $peerId) {
				$query['where'][] = [
					'type' => 'op',
					'operator' => '=',
					'params' => [
						[
							'type' => 'fld',
							'table' => 'base3system_sysallocview',
							'tablealias' => 'filterbyalloc',
							'field' => 'peer_id',
							'variant' => 'optional' // ensures LEFT JOIN, no record loss
						],
						$peerId
					]
				];
			}
		}

		// --- inalloc: OR-based IN filter ---
		if (!empty($options['inalloc'])) {
			$peers = is_array($options['inalloc']) ? $options['inalloc'] : [$options['inalloc']];
			$query['where'][] = [
				'type' => 'op',
				'operator' => 'IN',
				'params' => [
					[
						'type' => 'fld',
						'table' => 'base3system_sysallocview',
						'tablealias' => 'filterbyalloc',
						'field' => 'peer_id',
						'variant' => 'optional'
					],
					$peers
				]
			];
		}

		// --- excludealloc: NOT IN filter ---
		if (!empty($options['excludealloc'])) {
			$peers = is_array($options['excludealloc']) ? $options['excludealloc'] : [$options['excludealloc']];
			$query['where'][] = [
				'type' => 'op',
				'operator' => 'NOT IN',
				'params' => [
					[
						'type' => 'fld',
						'table' => 'base3system_sysallocview',
						'tablealias' => 'filterbyalloc',
						'field' => 'peer_id',
						'variant' => 'optional'
					],
					$peers
				]
			];
		}

		return $query;
	}

	public function processResult(array $rows, array $options): array {
		// No postprocessing required for alloc filters
		return $rows;
	}

	public function getPriority(): int {
		// Execute after base filters, before grouping
		return 120;
	}
}
