<?php declare(strict_types=1);

namespace Memora\Extension;

use Base3\Api\ISortable;
use Base3\Usermanager\Api\IUsermanager;
use Base3\Usermanager\Permission;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationRequest;
use Memora\Api\IMemoraRoleResolver;
use Memora\Api\IMemoraQueryExtension;
use Memora\Dto\VerifiedResourceQuery;

class LoadAccessExtension implements IMemoraQueryExtension, ISortable {

	/** Default public (unauthenticated) user ID */
	private const DEFAULT_USER_ID = 1;

	/** Default group ID for all authenticated members */
	private const DEFAULT_GROUP_ID = 1;

	public function __construct(
		private readonly IUsermanager $usermanager,
		private readonly IMemoraRoleResolver $roles,
		private readonly IAuthorizationService $authorization
	) {}

	public function isApplicable(array $options): bool {
		return !empty($options['loadaccess']) && !($options['__memora_verified_resource_query'] ?? null) instanceof VerifiedResourceQuery;
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

		$groupIds = $this->roles->getCurrentGroupIds();

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

	private function hasEntryAdminPermission(): bool {
		return $this->authorization->decide(new AuthorizationRequest('base3', Permission::for('entry', 'admin')))->isAllowed();
	}

	public function getPriority(): int {
		// Runs after data joins but before grouping
		return 970;
	}
}
