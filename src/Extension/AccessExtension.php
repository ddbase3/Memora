<?php declare(strict_types=1);

namespace Memora\Extension;

use Base3\Api\ISortable;
use Base3\Usermanager\Api\IUsermanager;
use Memora\Api\IMemoraQueryExtension;

class AccessExtension implements IMemoraQueryExtension, ISortable {

	/** Default public (unauthenticated) user ID */
	private const DEFAULT_USER_ID = 1;

	/** Default group ID for all authenticated members */
	private const DEFAULT_GROUP_ID = 1;

	private const ENTRY_ROLE_SCOPE = 'entry';

	private const ENTRY_ROLE_PERMISSIONS = [
		'view',
		'edit'
	];

	public function __construct(private readonly IUsermanager $usermanager) {}

	public function isApplicable(array $options): bool {
		$user = $this->usermanager->getUser();
		return !$user || $user->role != 'admin';
	}

	public function applyToQuery(array $query, array $options): array {
		$user = $this->usermanager->getUser();

		// No user context -> public user/default group access only.
		if (!$user) {
			$query['where'][] = [
				'type' => 'op',
				'operator' => 'OR',
				'params' => [
					$this->buildPublicUserAccessCondition(),
					$this->buildRoleAccessCondition([self::DEFAULT_USER_ID], [self::DEFAULT_GROUP_ID])
				]
			];
			return $query;
		}

		// Build base conditions
		$userConds = [];
		$groupConds = [];

		// Direct user access
		$userConds[] = [ 'type' => 'op', 'operator' => '=', 'params' => [
			[ 'type' => 'fld', 'table' => 'base3system_sysuseraccess', 'field' => 'user_id', 'variant' => 'required' ],
			$user->id
		]];

		// Public (default user) access, excluding owner-only
		$userConds[] = $this->buildPublicUserAccessCondition();

		// Group access (specific + default group)
		$groupIds = $this->getCurrentGroupIds();

		if (!empty($groupIds)) {
			$groupConds[] = [ 'type' => 'op', 'operator' => 'IN', 'params' => [
				[ 'type' => 'fld', 'table' => 'base3system_sysgroupaccess', 'field' => 'group_id', 'variant' => 'optional' ],
				$groupIds
			]];
		}

		// Combine direct user, group, public, and role-based access with OR.
		$combined = [ 'type' => 'op', 'operator' => 'OR', 'params' => [
			[ 'type' => 'op', 'operator' => 'OR', 'params' => $userConds ],
			[ 'type' => 'op', 'operator' => 'OR', 'params' => $groupConds ],
			$this->buildRoleAccessCondition([(int)$user->id, self::DEFAULT_USER_ID], $groupIds)
		]];

		$query['where'][] = $combined;
		return $query;
	}

	public function processResult(array $rows, array $options): array {
		return $rows;
	}

	private function buildPublicUserAccessCondition(): array {
		return [
			'type' => 'op',
			'operator' => 'AND',
			'params' => [
				[ 'type' => 'op', 'operator' => '=', 'params' => [
					[ 'type' => 'fld', 'table' => 'base3system_sysuseraccess', 'field' => 'user_id', 'variant' => 'required' ],
					self::DEFAULT_USER_ID
				]],
				[ 'type' => 'op', 'operator' => '!=', 'params' => [
					[ 'type' => 'fld', 'table' => 'base3system_sysuseraccess', 'field' => 'mode', 'variant' => 'required' ],
					'owner'
				]]
			]
		];
	}

	private function buildRoleAccessCondition(array $userIds, array $groupIds): array {
		$membershipConds = [];

		$userIds = array_values(array_unique(array_filter(
			array_map(static fn($id) => (int)$id, $userIds),
			static fn(int $id): bool => $id > 0
		)));

		if (!empty($userIds)) {
			$membershipConds[] = [ 'type' => 'op', 'operator' => 'IN', 'params' => [
				[ 'type' => 'fld', 'table' => 'base3system_sysuserrole', 'field' => 'user_id', 'variant' => 'optional' ],
				$userIds
			]];
		}

		if (!empty($groupIds)) {
			$membershipConds[] = [ 'type' => 'op', 'operator' => 'IN', 'params' => [
				[ 'type' => 'fld', 'table' => 'base3system_sysgrouprole', 'field' => 'group_id', 'variant' => 'optional' ],
				$groupIds
			]];
		}

		if (empty($membershipConds)) {
			return [
				'type' => 'op',
				'operator' => '=',
				'params' => [
					[ 'type' => 'fld', 'table' => 'base3system_sysentry', 'field' => 'id' ],
					-1
				]
			];
		}

		return [
			'type' => 'op',
			'operator' => 'IN',
			'params' => [
				[ 'type' => 'fld', 'table' => 'base3system_sysentry', 'field' => 'id' ],
				[
					'type' => 'subquery',
					'query' => [
						'type' => 'select',
						'table' => 'base3system_sysroleaccess',
						'fields' => [
							[
								'element' => [
									'type' => 'fld',
									'table' => 'base3system_sysroleaccess',
									'field' => 'entry_id'
								]
							]
						],
						'where' => [
							'type' => 'op',
							'operator' => 'AND',
							'params' => [
								[ 'type' => 'op', 'operator' => '=', 'params' => [
									[ 'type' => 'fld', 'table' => 'base3system_sysrole', 'field' => 'scope', 'variant' => 'required' ],
									self::ENTRY_ROLE_SCOPE
								]],
								[ 'type' => 'op', 'operator' => 'IN', 'params' => [
									[ 'type' => 'fld', 'table' => 'base3system_sysrole', 'field' => 'permission', 'variant' => 'required' ],
									self::ENTRY_ROLE_PERMISSIONS
								]],
								[ 'type' => 'op', 'operator' => '=', 'params' => [
									[ 'type' => 'fld', 'table' => 'base3system_sysrole', 'field' => 'archive', 'variant' => 'required' ],
									0
								]],
								[ 'type' => 'op', 'operator' => 'OR', 'params' => $membershipConds ]
							]
						]
					]
				]
			]
		];
	}

	private function getCurrentGroupIds(): array {
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

	public function getPriority(): int {
		return 780;
	}
}
