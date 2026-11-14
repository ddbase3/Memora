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

class LimitExtension implements IMemoraQueryExtension, ISortable {

	// Implementation of IMemoraQueryExtension

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
