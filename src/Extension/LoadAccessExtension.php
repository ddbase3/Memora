<?php declare(strict_types=1);

namespace Memora\Extension;

use Base3\Api\ISortable;
use Base3\Usermanager\Api\IUsermanager;
use Base3\Usermanager\Permission;
use Memora\Api\IMemoraQueryExtension;

class LoadAccessExtension implements IMemoraQueryExtension, ISortable {

	/** Default public (unauthenticated) user ID */
	private const DEFAULT_USER_ID = 1;

	/** Default group ID for all authenticated members */
	private const DEFAULT_GROUP_ID = 1;

	public function __construct(private readonly IUsermanager $usermanager) {}

	public function isApplicable(array $options): bool {
		return !empty($options['loadaccess']);
	}

	public function applyToQuery(array $query, array $options): array {
		$user = $this->usermanager->getUser();

		// --- Admins always have edit access ---
		if ($this->hasEntryAdminPermission()) {
			$query['fields'][] = [
				'element' => 'edit',
				'alias' => 'access'
			];
			return $query;
		}

		// --- Non-admin users and anonymous users: build access logic ---
		$currentUserId = $user ? (int)$user->id : self::DEFAULT_USER_ID;
		$userIds = [$currentUserId];
		if ($currentUserId !== self::DEFAULT_USER_ID) {
			$userIds[] = self::DEFAULT_USER_ID;
		}

		$groupIds = $this->getCurrentGroupIds($user !== null);

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
						'operator' => 'IN',
						'params' => [
							['type' => 'fld', 'tablealias' => 'ua', 'field' => 'user_id'],
							$userIds
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

		if ($currentUserId === self::DEFAULT_USER_ID) {
			$currentUserEditCondition = [
				'type' => 'op',
				'operator' => '=',
				'params' => [
					['type' => 'fld', 'tablealias' => 'ua', 'field' => 'mode'],
					'moderator'
				]
			];

			$userViewCondition = [
				'type' => 'op',
				'operator' => 'AND',
				'params' => [
					[ 'type' => 'op', 'operator' => '=', 'params' => [
						['type' => 'fld', 'tablealias' => 'ua', 'field' => 'user_id'],
						self::DEFAULT_USER_ID
					]],
					[ 'type' => 'op', 'operator' => '!=', 'params' => [
						['type' => 'fld', 'tablealias' => 'ua', 'field' => 'mode'],
						'owner'
					]]
				]
			];
		} else {
			$currentUserEditCondition = [
				'type' => 'op',
				'operator' => 'AND',
				'params' => [
					[ 'type' => 'op', 'operator' => '=', 'params' => [
						['type' => 'fld', 'tablealias' => 'ua', 'field' => 'user_id'],
						$currentUserId
					]],
					[ 'type' => 'op', 'operator' => 'IN', 'params' => [
						['type' => 'fld', 'tablealias' => 'ua', 'field' => 'mode'],
						['moderator', 'owner']
					]]
				]
			];

			$userViewCondition = [
				'type' => 'op',
				'operator' => 'OR',
				'params' => [
					[
						'type' => 'op',
						'operator' => 'AND',
						'params' => [
							[ 'type' => 'op', 'operator' => '=', 'params' => [
								['type' => 'fld', 'tablealias' => 'ua', 'field' => 'user_id'],
								$currentUserId
							]],
							[ 'type' => 'op', 'operator' => 'IS NOT NULL', 'params' => [
								['type' => 'fld', 'tablealias' => 'ua', 'field' => 'mode']
							]]
						]
					],
					[
						'type' => 'op',
						'operator' => 'AND',
						'params' => [
							[ 'type' => 'op', 'operator' => '=', 'params' => [
								['type' => 'fld', 'tablealias' => 'ua', 'field' => 'user_id'],
								self::DEFAULT_USER_ID
							]],
							[ 'type' => 'op', 'operator' => '!=', 'params' => [
								['type' => 'fld', 'tablealias' => 'ua', 'field' => 'mode'],
								'owner'
							]]
						]
					]
				]
			];
		}

		$publicUserEditCondition = [
			'type' => 'op',
			'operator' => 'AND',
			'params' => [
				[ 'type' => 'op', 'operator' => '=', 'params' => [
					['type' => 'fld', 'tablealias' => 'ua', 'field' => 'user_id'],
					self::DEFAULT_USER_ID
				]],
				[ 'type' => 'op', 'operator' => '=', 'params' => [
					['type' => 'fld', 'tablealias' => 'ua', 'field' => 'mode'],
					'moderator'
				]]
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
							'operator' => 'OR',
							'params' => [
								$currentUserEditCondition,
								$publicUserEditCondition
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
								$userViewCondition,
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

	private function getCurrentGroupIds(bool $hasUser): array {
		if (!$hasUser) {
			return [self::DEFAULT_GROUP_ID];
		}

		$groups = $this->usermanager->getGroups();
		$groupIds = array_map(fn($g) => (int)$g->id, $groups ?? []);

		if (!in_array(self::DEFAULT_GROUP_ID, $groupIds, true)) {
			$groupIds[] = self::DEFAULT_GROUP_ID;
		}

		return array_values(array_unique(array_filter(
			$groupIds,
			static fn(int $id): bool => $id > 0
		)));
	}

	private function hasEntryAdminPermission(): bool {
		return $this->usermanager->can(Permission::for('entry', 'admin'));
	}

	public function getPriority(): int {
		// Runs after data joins but before grouping
		return 970;
	}
}
