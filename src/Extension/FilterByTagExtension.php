<?php declare(strict_types=1);

namespace Memora\Extension;

use Base3\Api\ISortable;
use Memora\Api\IEntryQueryExtension;

class FilterByTagExtension implements IEntryQueryExtension, ISortable {

	public function isApplicable(array $options): bool {
		return !empty($options['tag'])
			|| !empty($options['intag'])
			|| !empty($options['excludetag']);
	}

	public function applyToQuery(array $query, array $options): array {
		// --- tag: AND-filter for each tag value ---
		if (!empty($options['tag'])) {
			$tags = is_array($options['tag']) ? $options['tag'] : [$options['tag']];
			foreach ($tags as $i => $tag) {
				$query['where'][] = [
					'type' => 'op',
					'operator' => '=',
					'params' => [
						[
							'type' => 'fld',
							'table' => 'base3system_systag',
							'tablealias' => 'tag' . $i,
							'field' => 'tag',
							'variant' => 'required'
						],
						$tag
					]
				];
			}
		}

		// --- intag: OR-based IN filter ---
		if (!empty($options['intag'])) {
			$tags = is_array($options['intag']) ? $options['intag'] : [$options['intag']];
			$query['where'][] = [
				'type' => 'op',
				'operator' => 'IN',
				'params' => [
					[
						'type' => 'fld',
						'table' => 'base3system_systag',
						'field' => 'tag',
						'variant' => 'optional'
					],
					$tags
				]
			];
		}

		// --- excludetag: NOT IN filter ---
		if (!empty($options['excludetag'])) {
			$tags = is_array($options['excludetag']) ? $options['excludetag'] : [$options['excludetag']];
			$query['where'][] = [
				'type' => 'op',
				'operator' => 'NOT IN',
				'params' => [
					[
						'type' => 'fld',
						'table' => 'base3system_systag',
						'field' => 'tag',
						'variant' => 'optional'
					],
					$tags
				]
			];
		}

		return $query;
	}

	public function processResult(array $rows, array $options): array {
		// No postprocessing required for tag filters
		return $rows;
	}

	public function getPriority(): int {
		// Execute after basic filters, before grouping
		return 130;
	}
}

