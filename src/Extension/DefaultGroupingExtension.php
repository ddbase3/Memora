<?php declare(strict_types=1);

namespace Memora\Extension;

use Base3\Api\ISortable;
use Memora\Api\IMemoraQueryExtension;

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
