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

class FilterByEntryExtension implements IMemoraQueryExtension, ISortable {

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

