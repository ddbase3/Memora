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

class DefaultGroupingExtension implements IMemoraQueryExtension, ISortable {

	public function isApplicable(array $options): bool {
		// Always applicable, acts as cleanup and fallback
		return true;
	}

	public function applyToQuery(array $query, array $options): array {
		// Deduplicate existing group_by entries
		if (!empty($query['group_by'])) {
			$uniqueGroups = [];
			foreach ($query['group_by'] as $group) {
				$key = json_encode($group);
				$uniqueGroups[$key] = $group;
			}
			$query['group_by'] = array_values($uniqueGroups);
		}

		// Ensure at least one group_by (id) exists to avoid duplicates
		if (empty($query['group_by'])) {
			$query['group_by'][] = [
				'type' => 'fld',
				'table' => 'base3system_sysentry',
				'field' => 'id'
			];
		}

		return $query;
	}

	public function processResult(array $rows, array $options): array {
		return $rows;
	}

	public function getPriority(): int {
		return 1000; // Should always execute last
	}
}
