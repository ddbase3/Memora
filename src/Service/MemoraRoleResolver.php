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
			if (!$this->roleMatchesPermissionFilter($role, $scope, $permissions)) continue;
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

		$roles = $this->fetchRows(
			'base3system_sysrole',
			['id', 'name', 'label', 'info', 'archive', 'created', 'changed'],
			$this->and($where),
			[
				['element' => $this->fld('base3system_sysrole', 'name'), 'direction' => 'ASC']
			]
		);

		return $this->withPermissions($roles, $includeArchived);
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

	private function withPermissions(array $roles, bool $includeArchived): array {
		$roleIds = $this->normalizeIds(array_column($roles, 'id'));
		if (empty($roleIds)) {
			return $roles;
		}

		$relations = $this->fetchRows(
			'base3system_sysrolepermission',
			['role_id', 'permission_id'],
			$this->in('base3system_sysrolepermission', 'role_id', $roleIds)
		);

		$permissionIds = $this->normalizeIds(array_column($relations, 'permission_id'));
		$permissions = [];

		if (!empty($permissionIds)) {
			$where = [$this->in('base3system_syspermission', 'id', $permissionIds)];
			if (!$includeArchived) {
				$where[] = $this->eq('base3system_syspermission', 'archive', 0);
			}

			foreach ($this->fetchRows('base3system_syspermission', ['id', 'scope', 'permission', 'label', 'info', 'archive', 'created', 'changed'], $this->and($where)) as $permission) {
				$id = $this->normalizeId($permission['id'] ?? null);
				if ($id !== null) {
					$permissions[$id] = $permission;
				}
			}
		}

		$byRole = [];
		foreach ($relations as $relation) {
			$roleId = $this->normalizeId($relation['role_id'] ?? null);
			$permissionId = $this->normalizeId($relation['permission_id'] ?? null);
			if ($roleId === null || $permissionId === null || !isset($permissions[$permissionId])) continue;
			$byRole[$roleId][] = $permissions[$permissionId];
		}

		foreach ($roles as &$role) {
			$roleId = $this->normalizeId($role['id'] ?? null);
			$role['permissions'] = $roleId !== null ? ($byRole[$roleId] ?? []) : [];
		}
		unset($role);

		return $roles;
	}

	private function roleMatchesPermissionFilter(array $role, ?string $scope, array $permissions): bool {
		if ($scope === null && empty($permissions)) {
			return true;
		}

		foreach (($role['permissions'] ?? []) as $permission) {
			if ($scope !== null && (string)($permission['scope'] ?? '') !== $scope) continue;
			if (!empty($permissions) && !in_array((string)($permission['permission'] ?? ''), $permissions, true)) continue;
			return true;
		}

		return false;
	}
}
