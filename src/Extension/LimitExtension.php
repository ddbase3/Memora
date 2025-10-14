<?php declare(strict_types=1);

namespace Memora\Extension;

use Base3\Api\ISortable;
use Memora\Api\IEntryQueryExtension;

class LimitExtension implements IEntryQueryExtension, ISortable {

	// Implementation of IEntryQueryExtension

	public function isApplicable(array $options): bool {
		return !empty($options['limitcount']);
	}

	public function applyToQuery(array $query, array $options): array {
		// Apply limit and optional offset
		$query['limit'] = (int)$options['limitcount'];
		if (!empty($options['limitoffset'])) {
			$query['offset'] = (int)$options['limitoffset'];
		}
		return $query;
	}

	public function processResult(array $rows, array $options): array {
		// No postprocessing required for limits
		return $rows;
	}

	// Implementation of ISortable

	public function getPriority(): int {
		return 950;
	}
}
