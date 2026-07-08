<?php declare(strict_types=1);

namespace Memora\Service;

use Base3\Usermanager\Api\IUsermanager;
use Memora\Api\IMemoraQueryService;
use Memora\Api\IMemoraRoleResolver;

class MemoraRoleResolver extends AbstractMemoraTableService implements IMemoraRoleResolver {

	private const DEFAULT_USER_ID = 1;
	private const DEFAULT_GROUP_ID = 1;

	public function __construct(
		IMemoraQueryService $dataqueryservice,
		private readonly IUsermanager $usermanager
	) {
		parent::__construct($dataqueryservice);
	}

	public function getCurrentUserIds(bool $includePublic = true): array {
		$user = $this->usermanager->getUser();
		$ids = [];

		if ($user && !empty($user->id)) {
			$ids[] = (int)$user->id;
		}
		if ($includePublic) {
			$ids[] = self::DEFAULT_USER_ID;
		}

		return $this->normalizeIds($ids);
	}

	public function getCurrentGroupIds(bool $includeDefault = true): array {
		$groups = $this->usermanager->getGroups();
		$ids = [];

		foreach (($groups ?? []) as $group) {
			if (!empty($group->id)) {
				$ids[] = (int)$group->id;
			}
		}
		if ($includeDefault) {
			$ids[] = self::DEFAULT_GROUP_ID;
		}

		return $this->normalizeIds($ids);
	}

	public function getUserRoleIds(int|string $userId, bool $includeArchived = false): array {
		$userId = $this->normalizeId($userId);
		if ($userId === null) {
			return [];
		}

		$rows = $this->fetchRows(
			'base3system_sysuserrole',
			['role_id'],
			$this->eq('base3system_sysuserrole', 'user_id', $userId)
		);

		return $this->filterRoleIds(array_column($rows, 'role_id'), $includeArchived);
	}

	public function getGroupRoleIds(int|string $groupId, bool $includeArchived = false): array {
		$groupId = $this->normalizeId($groupId);
		if ($groupId === null) {
			return [];
		}

		$rows = $this->fetchRows(
			'base3system_sysgrouprole',
			['role_id'],
			$this->eq('base3system_sysgrouprole', 'group_id', $groupId)
		);

		return $this->filterRoleIds(array_column($rows, 'role_id'), $includeArchived);
	}

	public function getEffectiveUserRoleIds(int|string $userId, bool $includeArchived = false): array {
		$userId = $this->normalizeId($userId);
		if ($userId === null) {
			return [];
		}

		$groupIds = $this->getUserGroupIds($userId);
		return $this->getRoleIdsForUsersAndGroups([$userId], $groupIds, null, [], $includeArchived);
	}

	public function getEffectiveUserRoles(int|string $userId, bool $includeArchived = false): array {
		return $this->getRolesByIds(
			$this->getEffectiveUserRoleIds($userId, $includeArchived),
			$includeArchived
		);
	}

	public function getRoleIdsForUsersAndGroups(array $userIds, array $groupIds, ?string $scope = null, array $permissions = [], bool $includeArchived = false): array {
		$userIds = $this->normalizeIds($userIds);
		$groupIds = $this->normalizeIds($groupIds);
		$roleIds = [];

		if (!empty($userIds)) {
			$rows = $this->fetchRows(
				'base3system_sysuserrole',
				['role_id'],
				$this->in('base3system_sysuserrole', 'user_id', $userIds)
			);
			$roleIds = array_merge($roleIds, array_column($rows, 'role_id'));
		}

		if (!empty($groupIds)) {
			$rows = $this->fetchRows(
				'base3system_sysgrouprole',
				['role_id'],
				$this->in('base3system_sysgrouprole', 'group_id', $groupIds)
			);
			$roleIds = array_merge($roleIds, array_column($rows, 'role_id'));
		}

		$roles = $this->getRolesByIds($roleIds, $includeArchived);
		$permissions = $this->normalizeStrings($permissions);
		$scope = $scope !== null ? trim($scope) : null;
		$result = [];

		foreach ($roles as $role) {
			if ($scope !== null && (string)($role['scope'] ?? '') !== $scope) continue;
			if (!empty($permissions) && !in_array((string)($role['permission'] ?? ''), $permissions, true)) continue;
			$id = $this->normalizeId($role['id'] ?? null);
			if ($id !== null) {
				$result[$id] = $id;
			}
		}

		return array_values($result);
	}

	public function getRolesByIds(array $roleIds, bool $includeArchived = false): array {
		$roleIds = $this->normalizeIds($roleIds);
		if (empty($roleIds)) {
			return [];
		}

		$where = [$this->in('base3system_sysrole', 'id', $roleIds)];
		if (!$includeArchived) {
			$where[] = $this->eq('base3system_sysrole', 'archive', 0);
		}

		return $this->fetchRows(
			'base3system_sysrole',
			['id', 'name', 'scope', 'permission', 'label', 'info', 'archive', 'created', 'changed'],
			$this->and($where),
			[
				['element' => $this->fld('base3system_sysrole', 'scope'), 'direction' => 'ASC'],
				['element' => $this->fld('base3system_sysrole', 'name'), 'direction' => 'ASC']
			]
		);
	}

	private function getUserGroupIds(int $userId): array {
		$rows = $this->fetchRows(
			'base3system_sysusergroup',
			['group_id'],
			$this->eq('base3system_sysusergroup', 'user_id', $userId)
		);

		return $this->normalizeIds(array_column($rows, 'group_id'));
	}

	private function filterRoleIds(array $roleIds, bool $includeArchived): array {
		$roles = $this->getRolesByIds($roleIds, $includeArchived);
		return $this->normalizeIds(array_column($roles, 'id'));
	}
}
