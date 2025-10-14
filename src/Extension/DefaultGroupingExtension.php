<?php declare(strict_types=1);

namespace Memora\Extension;

use Base3\Api\ISortable;
use Memora\Api\IEntryQueryExtension;

class DefaultGroupingExtension implements IEntryQueryExtension, ISortable {

	// Implementation of IEntryQueryExtension

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

		// Add default group_by if none defined and aggregates exist
		if (empty($query['group_by'])) {
			foreach ($query['fields'] as $field) {
				if (($field['element']['type'] ?? '') === 'fn') {
					$query['group_by'][] = [
						'type' => 'fld',
						'table' => 'base3system_sysentry',
						'field' => 'id'
					];
					break;
				}
			}
		}

		return $query;
	}

	public function processResult(array $rows, array $options): array {
		// No postprocessing required
		return $rows;
	}

	// Implementation of ISortable

	public function getPriority(): int {
		return 1000;
	}
}
