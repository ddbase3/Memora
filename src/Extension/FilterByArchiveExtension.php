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

/**
 * FilterByArchiveExtension
 *
 * Filters entries by archive state:
 *   "none" (default): only non-archived entries
 *   "all": all entries regardless of archive flag
 *   "archived": only archived entries
 */
class FilterByArchiveExtension implements IMemoraQueryExtension, ISortable {

	public function isApplicable(array $options): bool {
		// Always applicable, since "archive" has a default value
		return isset($options['archive']);
	}

	public function applyToQuery(array $query, array $options): array {
		$archiveMode = $options['archive'] ?? 'none';

		// "all" → no filter
		if ($archiveMode === 'all') {
			return $query;
		}

		// "archived" → e.archive = 1
		if ($archiveMode === 'archived') {
			$query['where'][] = [
				'type' => 'op',
				'operator' => '=',
				'params' => [
					[
						'type' => 'fld',
						'table' => 'base3system_sysentry',
						'field' => 'archive'
					],
					1
				]
			];
			return $query;
		}

		// Default: only non-archived (archive = 0)
		$query['where'][] = [
			'type' => 'op',
			'operator' => '=',
			'params' => [
				[
					'type' => 'fld',
					'table' => 'base3system_sysentry',
					'field' => 'archive'
				],
				0
			]
		];

		return $query;
	}

	public function processResult(array $rows, array $options): array {
		// No postprocessing required for archive filtering
		return $rows;
	}

	public function getPriority(): int {
		// Run early, after BaseFieldsExtension but before tag/alloc filters
		return 110;
	}
}
