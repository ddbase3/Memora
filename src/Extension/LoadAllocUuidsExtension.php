<?php declare(strict_types=1);

namespace Memora\Extension;

use Base3\Api\ISortable;
use Memora\Api\IMemoraQueryExtension;

class LoadAllocUuidsExtension implements IMemoraQueryExtension, ISortable {

	public function isApplicable(array $options): bool {
		return !empty($options['loadallocuuids']);
	}

	public function applyToQuery(array $query, array $options): array {
		$query['leftjoin'][] = [
			'table' => 'base3system_sysallocview',
			'alias' => 'loadallocuuids_alloc',
			'on' => [
				'type' => 'op',
				'operator' => '=',
				'params' => [
					[
						'type' => 'fld',
						'tablealias' => 'loadallocuuids_alloc',
						'field' => 'entry_id'
					],
					[
						'type' => 'fld',
						'table' => 'base3system_sysentry',
						'field' => 'id'
					]
				]
			]
		];

		$query['leftjoin'][] = [
			'table' => 'base3system_sysentry',
			'alias' => 'loadallocuuids_peer',
			'on' => [
				'type' => 'op',
				'operator' => '=',
				'params' => [
					[
						'type' => 'fld',
						'tablealias' => 'loadallocuuids_peer',
						'field' => 'id'
					],
					[
						'type' => 'fld',
						'tablealias' => 'loadallocuuids_alloc',
						'field' => 'peer_id'
					]
				]
			]
		];

		$query['fields'][] = [
			'element' => [
				'type' => 'fn',
				'function' => 'GROUP_CONCAT',
				'params' => [
					[
						'type' => 'fn',
						'function' => 'DISTINCT',
						'params' => [
							[
								'type' => 'fn',
								'function' => 'HEX',
								'params' => [
									[
										'type' => 'fld',
										'tablealias' => 'loadallocuuids_peer',
										'field' => 'uuid'
									]
								]
							]
						]
					]
				]
			],
			'alias' => 'allocuuids'
		];

		return $query;
	}

	public function processResult(array $rows, array $options): array {
		foreach ($rows as &$row) {
			if (!empty($row['allocuuids']) && is_string($row['allocuuids'])) {
				$uuids = explode(',', $row['allocuuids']);
				$uuids = array_map('trim', $uuids);
				$uuids = array_filter($uuids, fn($uuid) => $uuid !== '');
				$uuids = array_map('strtolower', $uuids);
				$uuids = array_values(array_unique($uuids));

				$row['allocuuids'] = $uuids;
			} else {
				$row['allocuuids'] = [];
			}
		}
		unset($row);

		return $rows;
	}

	public function getPriority(): int {
		return 845;
	}
}
