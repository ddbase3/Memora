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
