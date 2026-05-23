<?php declare(strict_types=1);

namespace Memora\Extension;

use Base3\Api\ISortable;
use Memora\Api\IMemoraQueryExtension;

class LoadTagsExtension implements IMemoraQueryExtension, ISortable {

	// Implementation of IMemoraQueryExtension

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
		foreach ($rows as &$row) {
			$row['tags'] = $this->normalizeTags($row['tags'] ?? null);
		}
		unset($row);

		return $rows;
	}

	protected function normalizeTags(mixed $value): array {
		if ($value === null || $value === '') {
			return [];
		}

		if (is_array($value)) {
			$tags = $value;
		} else {
			$tags = explode(',', (string) $value);
		}

		$tags = array_map('trim', $tags);
		$tags = array_filter($tags, static fn(string $tag): bool => $tag !== '');
		$tags = array_unique($tags);

		return array_values($tags);
	}

	// Implementation of ISortable

	public function getPriority(): int {
		return 850;
	}
}
