<?php declare(strict_types=1);

namespace Memora\Extension;

use Base3\Api\ISortable;
use Memora\Api\IMemoraQueryExtension;

class FilterByCustomSqlExtension implements IMemoraQueryExtension, ISortable {

	public function isApplicable(array $options): bool {
		// Applicable only if a structured filter element is provided
		return !empty($options['filter']) && is_array($options['filter']);
	}

	public function applyToQuery(array $query, array $options): array {
		$filter = $options['filter'];

		// Ensure WHERE clause exists
		if (empty($query['where'])) $query['where'] = [];

		// Append structured filter element directly
		$query['where'][] = $filter;

		return $query;
	}

	public function processResult(array $rows, array $options): array {
		// No postprocessing required
		return $rows;
	}

	public function getPriority(): int {
		// Execute after field-based filters
		return 900;
	}
}
