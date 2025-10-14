<?php declare(strict_types=1);

namespace Memora\Extension;

use Base3\Api\ISortable;
use Memora\Api\IEntryQueryExtension;

class FilterByAllocExtension implements IEntryQueryExtension, ISortable {

	public function isApplicable(array $options): bool {
		return !empty($options['alloc'])
			|| !empty($options['inalloc'])
			|| !empty($options['excludealloc']);
	}

	public function applyToQuery(array $query, array $options): array {
		// --- alloc: AND-filter for each peer_id ---
		if (!empty($options['alloc'])) {
			$peers = is_array($options['alloc']) ? $options['alloc'] : [$options['alloc']];
			foreach ($peers as $i => $peerId) {
				$query['where'][] = [
					'type' => 'op',
					'operator' => '=',
					'params' => [
						[
							'type' => 'fld',
							'table' => 'base3system_sysallocview',
							'tablealias' => 'alloc' . $i,
							'field' => 'peer_id',
							'variant' => 'optional'
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
		// Execute after basic filters, before grouping
		return 120;
	}
}

