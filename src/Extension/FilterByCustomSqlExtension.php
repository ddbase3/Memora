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
