<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of Memora for BASE3 Framework.
 *
 * Memora is a modular XRM/CRM backend and knowledge graph for BASE3.
 * It provides structured entities, relations, metadata and tags.
 * ResourceFoundation contracts govern its shared entity services.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de
 * https://github.com/ddbase3/Memora
 **********************************************************************/

namespace Base3\Memora\Extension;

use Base3\CoreFoundation\Api\ISortable;
use Base3\Memora\Api\IMemoraQueryExtension;

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
