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

class FilterByTypeExtension implements IMemoraQueryExtension, ISortable {

	// Implementation of IMemoraQueryExtension

	public function isApplicable(array $options): bool {
		return !empty($options['type']);
	}

	public function applyToQuery(array $query, array $options): array {
		// Add filter by type alias (supports single or multiple types)
		$types = is_array($options['type']) ? $options['type'] : [$options['type']];
		$query['where'][] = [
			'type' => 'op',
			'operator' => count($types) === 1 ? '=' : 'IN',
			'params' => [
				[
					'type' => 'fld',
					'table' => 'base3system_systype',
					'field' => 'alias',
					'variant' => 'required'
				],
				count($types) === 1 ? $types[0] : $types
			]
		];
		return $query;
	}

	public function processResult(array $rows, array $options): array {
		// No postprocessing required for type filter
		return $rows;
	}
	
	// Implementation of ISortable

	public function getPriority(): int {
		return 110;
	}
}
