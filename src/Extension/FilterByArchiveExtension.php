<?php declare(strict_types=1);

namespace Memora\Extension;

use Base3\Api\ISortable;
use Memora\Api\IMemoraQueryExtension;

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
