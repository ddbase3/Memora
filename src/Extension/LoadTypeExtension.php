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

class LoadTypeExtension implements IMemoraQueryExtension, ISortable {

	public function isApplicable(array $options): bool {
		return !empty($options['loadtype']);
	}

	public function applyToQuery(array $query, array $options): array {
		// Add field for type alias (as "type")
		$query['fields'][] = [
			'element' => [
				'type' => 'fld',
				'table' => 'base3system_systype',
				'field' => 'alias'
			],
			'alias' => 'type'
		];

		return $query;
	}

	public function processResult(array $rows, array $options): array {
		// No transformation needed; "type" already present in rows
		return $rows;
	}

	public function getPriority(): int {
		// Execute after name and tag loads, before grouping
		return 800;
	}
}
