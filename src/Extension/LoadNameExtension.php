<?php declare(strict_types=1);

namespace Memora\Extension;

use Base3\Api\ISortable;
use Memora\Api\IEntryQueryExtension;

class LoadNameExtension implements IEntryQueryExtension, ISortable {

	public function isApplicable(array $options): bool {
		return !empty($options['loadname']);
	}

	public function applyToQuery(array $query, array $options): array {
		// Add field for entry name
		$query['fields'][] = [
			'element' => [
				'type' => 'fld',
				'table' => 'base3system_sysname',
				'field' => 'name',
				'variant' => 'optional'
			],
			'alias' => 'name'
		];
		return $query;
	}

	public function processResult(array $rows, array $options): array {
		// No postprocessing required for name loading
		return $rows;
	}

	public function getPriority(): int {
		// Execute late, before grouping and after filters
		return 810;
	}
}
