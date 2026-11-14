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

class OrderExtension implements IMemoraQueryExtension, ISortable {

	public function isApplicable(array $options): bool {
		// Always applicable unless explicitly disabled
		return true;
	}

	public function applyToQuery(array $query, array $options): array {
		// Apply default or user-defined ordering
		$field = $options['orderfield'] ?? 'id';
		$direction = strtoupper($options['orderdir'] ?? 'DESC');

		$query['order_by'] = [
			[
				'element' => [
					'type' => 'fld',
					'table' => 'base3system_sysentry',
					'field' => $field
				],
				'direction' => in_array($direction, ['ASC', 'DESC'], true) ? $direction : 'DESC'
			]
		];

		return $query;
	}

	public function processResult(array $rows, array $options): array {
		// No postprocessing required for ordering
		return $rows;
	}

	public function getPriority(): int {
		// Execute after filters, before grouping
		return 900;
	}
}
