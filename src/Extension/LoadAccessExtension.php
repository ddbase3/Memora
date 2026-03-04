<?php declare(strict_types=1);

namespace Memora\Extension;

use Base3\Api\ISortable;
use Base3\Usermanager\Api\IUsermanager;
use Memora\Api\IMemoraQueryExtension;

class LoadAccessExtension implements IMemoraQueryExtension, ISortable {

	public function __construct(private readonly IUsermanager $usermanager) {}

	public function isApplicable(array $options): bool {
		return !empty($options['loadaccess']);
	}

	public function applyToQuery(array $query, array $options): array {
		$user = $this->usermanager->getUser();
		if (!$user) return $query;

		// --- Admins always have edit access ---
		if ($user->role === 'admin') {
			$query['fields'][] = [
				'element' => 'edit',
				'alias' => 'access'
			];
			return $query;
		}

		// --- Non-admin users: build access logic ---
		$groups = $this->usermanager->getGroups();
		$groupIds = array_map(fn($g) => (int)$g->id, $groups ?? []);
		if (empty($groupIds)) $groupIds = [0]; // avoid empty IN ()

		// Join useraccess
		$query['leftjoin'][] = [
			'table' => 'base3system_sysuseraccess',
			'alias' => 'ua',
			'on' => [
				'type' => 'op',
				'operator' => 'AND',
				'params' => [
					[
						'type' => 'op',
						'operator' => '=',
						'params' => [
							['type' => 'fld', 'tablealias' => 'ua', 'field' => 'entry_id'],
							['type' => 'fld', 'table' => 'base3system_sysentry', 'field' => 'id']
						]
					],
					[
						'type' => 'op',
						'operator' => '=',
						'params' => [
							['type' => 'fld', 'tablealias' => 'ua', 'field' => 'user_id'],
							$user->id
						]
					]
				]
			]
		];

		// Join groupaccess
		$query['leftjoin'][] = [
			'table' => 'base3system_sysgroupaccess',
			'alias' => 'ga',
			'on' => [
				'type' => 'op',
				'operator' => 'AND',
				'params' => [
					[
						'type' => 'op',
						'operator' => '=',
						'params' => [
							['type' => 'fld', 'tablealias' => 'ga', 'field' => 'entry_id'],
							['type' => 'fld', 'table' => 'base3system_sysentry', 'field' => 'id']
						]
					],
					[
						'type' => 'op',
						'operator' => 'IN',
						'params' => [
							['type' => 'fld', 'tablealias' => 'ga', 'field' => 'group_id'],
							$groupIds
						]
					]
				]
			]
		];

		// CASE-based access resolution
		$query['fields'][] = [
			'element' => [
				'type' => 'case',
				'cases' => [
					[
						'when' => [
							'type' => 'op',
							'operator' => 'IN',
							'params' => [
								['type' => 'fld', 'tablealias' => 'ua', 'field' => 'mode'],
								['moderator', 'owner']
							]
						],
						'then' => 'edit'
					],
					[
						'when' => [
							'type' => 'op',
							'operator' => '=',
							'params' => [
								['type' => 'fld', 'tablealias' => 'ga', 'field' => 'mode'],
								'moderator'
							]
						],
						'then' => 'edit'
					],
					[
						'when' => [
							'type' => 'op',
							'operator' => 'OR',
							'params' => [
								[
									'type' => 'op',
									'operator' => 'IS NOT NULL',
									'params' => [
										['type' => 'fld', 'tablealias' => 'ua', 'field' => 'mode']
									]
								],
								[
									'type' => 'op',
									'operator' => 'IS NOT NULL',
									'params' => [
										['type' => 'fld', 'tablealias' => 'ga', 'field' => 'mode']
									]
								]
							]
						],
						'then' => 'view'
					]
				],
				'else' => 'none'
			],
			'alias' => 'access'
		];

		return $query;
	}

	public function processResult(array $rows, array $options): array {
		return $rows;
	}

	public function getPriority(): int {
		// Runs after data joins but before grouping
		return 970;
	}
}

