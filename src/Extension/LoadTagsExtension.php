<?php declare(strict_types=1);

namespace Memora\Extension;

use Base3\Api\ISortable;
use Memora\Api\IEntryQueryExtension;

class LoadTagsExtension implements IEntryQueryExtension, ISortable {

	// Implementation of IEntryQueryExtension

	public function isApplicable(array $options): bool {
		return !empty($options['loadtags']);
	}

	public function applyToQuery(array $query, array $options): array {
		// Add tag aggregation field
		$query['fields'][] = [
			'element' => [
				'type' => 'fn',
				'function' => 'GROUP_CONCAT',
				'params' => [
					[
						'type' => 'fld',
						'table' => 'base3system_systag',
						'field' => 'tag',
						'variant' => 'optional'
					]
				]
			],
			'alias' => 'tags'
		];

		return $query;
	}

	public function processResult(array $rows, array $options): array {
		// Split comma-separated tags into arrays
		foreach ($rows as &$row) {
			if (isset($row['tags']) && is_string($row['tags'])) {
				$row['tags'] = array_unique(array_filter(array_map('trim', explode(',', $row['tags']))));
			}
		}
		unset($row);
		return $rows;
	}

	// Implementation of ISortable

	public function getPriority(): int {
		return 850;
	}
}
