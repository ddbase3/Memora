<?php declare(strict_types=1);

namespace Memora\Service;

use Base3\Authorization\Api\IAuthorizationCatalogService;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationRequest;
use Base3\Usermanager\Permission;
use Memora\Api\IMemoraQueryService;
use Memora\Api\IMemoraRoleResolver;
use RuntimeException;

/** Owns Memora's existing system role/permission catalog and direct membership tables. */
final class MemoraAuthorizationCatalogService extends AbstractMemoraTableService implements IAuthorizationCatalogService {

	public function __construct(
		IMemoraQueryService $query,
		private readonly IMemoraRoleResolver $roleResolver,
		private readonly IAuthorizationService $authorization
	) { parent::__construct($query); }

	private function assertAdministrator(): void {
		if (!$this->authorization->decide(new AuthorizationRequest('base3', Permission::for('system', 'admin')))->isAllowed()) {
			throw new RuntimeException('XRM role catalog administration denied.');
		}
	}
	public function getRoles(bool $includeArchived = false): array {
		$this->assertAdministrator();
		$where = $includeArchived ? null : $this->eq('base3system_sysrole', 'archive', 0);

		$roles = $this->fetchRows(
			'base3system_sysrole',
			$this->roleFields(),
			$where,
			[
				['element' => $this->fld('base3system_sysrole', 'name'), 'direction' => 'ASC']
			]
		);

		return $this->withRolePermissions($roles, $includeArchived);
	}

	public function getRole(int|string $roleId): ?array {
		$this->assertAdministrator();
		$roleId = $this->requireId($roleId, 'role');
		$role = $this->fetchRow('base3system_sysrole', $this->roleFields(), $this->eq('base3system_sysrole', 'id', $roleId));
		if (!$role) {
			return null;
		}

		$roles = $this->withRolePermissions([$role], true);
		return $roles[0] ?? $role;
	}

	public function createRole(array $role): int|string {
		$this->assertAdministrator();
		$name = $this->requireString((string)($role['name'] ?? ''), 'role name');

		$permissionIds = $this->resolvePermissionIdsFromPayload($role);
		$this->validateExistingIds($permissionIds, 'base3system_syspermission', 'permission');

		$insertId = $this->insertRow('base3system_sysrole', [
			'name' => $name,
			'label' => array_key_exists('label', $role) ? (string)$role['label'] : null,
			'info' => array_key_exists('info', $role) ? (string)$role['info'] : null,
			'archive' => !empty($role['archive']) ? 1 : 0
		], true);

		if (!$insertId) {
			$existing = $this->fetchRow('base3system_sysrole', ['id'], $this->eq('base3system_sysrole', 'name', $name));
			$insertId = $existing['id'] ?? 0;
		}

		$roleId = $this->normalizeId($insertId);
		if ($roleId !== null) {
			if (!empty($permissionIds)) {
				$this->replaceRolePermissions($roleId, $permissionIds);
			}
		}

		if (!is_numeric($insertId) || (int)$insertId < 1) throw new \RuntimeException('Native role creation failed.');
		return $insertId;
	}

	public function updateRole(int|string $roleId, array $patch): void {
		$this->assertAdministrator();
		$roleId = $this->requireId($roleId, 'role');
		$set = [];

		foreach (['name', 'label', 'info'] as $field) {
			if (array_key_exists($field, $patch)) {
				$set[$field] = $patch[$field] === null ? null : (string)$patch[$field];
			}
		}
		if (array_key_exists('archive', $patch)) {
			$set['archive'] = !empty($patch['archive']) ? 1 : 0;
		}

		$permissionIds = $this->containsPermissionPayload($patch) ? $this->resolvePermissionIdsFromPayload($patch) : null;
		if ($permissionIds !== null) $this->validateExistingIds($permissionIds, 'base3system_syspermission', 'permission');
		$this->updateRows('base3system_sysrole', $set, $this->eq('base3system_sysrole', 'id', $roleId), 1);
		if ($permissionIds !== null) $this->replaceRolePermissions($roleId, $permissionIds);
	}

	public function archiveRole(int|string $roleId): void {
		$this->assertAdministrator();
		$this->updateRole($roleId, ['archive' => 1]);
	}

	public function getPermissions(bool $includeArchived = false): array {
		$this->assertAdministrator();
		$where = $includeArchived ? null : $this->eq('base3system_syspermission', 'archive', 0);

		return $this->fetchRows(
			'base3system_syspermission',
			$this->permissionFields(),
			$where,
			[
				['element' => $this->fld('base3system_syspermission', 'scope'), 'direction' => 'ASC'],
				['element' => $this->fld('base3system_syspermission', 'permission'), 'direction' => 'ASC']
			]
		);
	}

	public function getPermission(int|string $permissionId): ?array {
		$this->assertAdministrator();
		$permissionId = $this->requireId($permissionId, 'permission');
		return $this->fetchRow('base3system_syspermission', $this->permissionFields(), $this->eq('base3system_syspermission', 'id', $permissionId));
	}

	public function createPermission(array $permission): int|string {
		$this->assertAdministrator();
		$scope = $this->requireString((string)($permission['scope'] ?? ''), 'permission scope');
		$name = $this->requireString((string)($permission['permission'] ?? ''), 'permission name');

		$insertId = $this->insertRow('base3system_syspermission', [
			'scope' => $scope,
			'permission' => $name,
			'label' => array_key_exists('label', $permission) ? (string)$permission['label'] : null,
			'info' => array_key_exists('info', $permission) ? (string)$permission['info'] : null,
			'archive' => !empty($permission['archive']) ? 1 : 0
		], true);

		if ($insertId) {
			return $insertId;
		}

		$existing = $this->fetchRow(
			'base3system_syspermission',
			['id'],
			$this->and([
				$this->eq('base3system_syspermission', 'scope', $scope),
				$this->eq('base3system_syspermission', 'permission', $name)
			])
		);

		return $existing['id'] ?? 0;
	}

	public function updatePermission(int|string $permissionId, array $patch): void {
		$this->assertAdministrator();
		$permissionId = $this->requireId($permissionId, 'permission');
		$set = [];

		foreach (['scope', 'permission', 'label', 'info'] as $field) {
			if (array_key_exists($field, $patch)) {
				$set[$field] = $patch[$field] === null ? null : (string)$patch[$field];
			}
		}
		if (array_key_exists('archive', $patch)) {
			$set['archive'] = !empty($patch['archive']) ? 1 : 0;
		}

		$this->updateRows('base3system_syspermission', $set, $this->eq('base3system_syspermission', 'id', $permissionId), 1);
	}

	public function archivePermission(int|string $permissionId): void {
		$this->assertAdministrator();
		$this->updatePermission($permissionId, ['archive' => 1]);
	}

	public function getRolePermissions(int|string $roleId): array {
		$this->assertAdministrator();
		$roleId = $this->requireId($roleId, 'role');
		$relations = $this->fetchRows(
			'base3system_sysrolepermission',
			['permission_id'],
			$this->eq('base3system_sysrolepermission', 'role_id', $roleId)
		);
		$permissionIds = $this->normalizeIds(array_column($relations, 'permission_id'));

		if (empty($permissionIds)) {
			return [];
		}

		return $this->fetchRows(
			'base3system_syspermission',
			$this->permissionFields(),
			$this->in('base3system_syspermission', 'id', $permissionIds),
			[
				['element' => $this->fld('base3system_syspermission', 'scope'), 'direction' => 'ASC'],
				['element' => $this->fld('base3system_syspermission', 'permission'), 'direction' => 'ASC']
			]
		);
	}

	public function replaceRolePermissions(int|string $roleId, array $permissionIds): void {
		$this->assertAdministrator();
		$roleId = $this->requireId($roleId, 'role');
		$this->validateExistingIds([$roleId], 'base3system_sysrole', 'role');
		$permissionIds = $this->requireIds($permissionIds, 'permission');
		$this->validateExistingIds($permissionIds, 'base3system_syspermission', 'permission');
		$queries = [[
			'type' => 'delete',
			'table' => 'base3system_sysrolepermission',
			'where' => $this->eq('base3system_sysrolepermission', 'role_id', $roleId)
		]];

		if (!empty($permissionIds)) {
			$values = [];
			foreach ($permissionIds as $permissionId) {
				$values[] = ['role_id' => $roleId, 'permission_id' => $permissionId];
			}
			$queries[] = [
				'type' => 'insert',
				'ignore' => true,
				'table' => 'base3system_sysrolepermission',
				'values' => $values
			];
		}

		$this->transaction($queries);
	}

	public function getUserRoles(int|string $userId): array {
		$this->assertAdministrator();
		return $this->roleResolver->getRolesByIds($this->roleResolver->getUserRoleIds($userId));
	}

	public function getGroupRoles(int|string $groupId): array {
		$this->assertAdministrator();
		return $this->roleResolver->getRolesByIds($this->roleResolver->getGroupRoleIds($groupId));
	}

	public function getEffectiveUserRoles(int|string $userId): array {
		$this->assertAdministrator();
		return $this->roleResolver->getEffectiveUserRoles($userId);
	}

	public function replaceUserRoles(int|string $userId, array $roleIds): void {
		$this->assertAdministrator();
		$userId = $this->requireId($userId, 'user');
		$this->validateExistingIds([$userId], 'base3system_sysuser', 'user');
		$roleIds = $this->requireIds($roleIds, 'role');
		$this->validateExistingIds($roleIds, 'base3system_sysrole', 'role');
		$queries = [[
			'type' => 'delete',
			'table' => 'base3system_sysuserrole',
			'where' => $this->eq('base3system_sysuserrole', 'user_id', $userId)
		]];

		if (!empty($roleIds)) {
			$values = [];
			foreach ($roleIds as $roleId) {
				$values[] = ['user_id' => $userId, 'role_id' => $roleId];
			}
			$queries[] = [
				'type' => 'insert',
				'ignore' => true,
				'table' => 'base3system_sysuserrole',
				'values' => $values
			];
		}

		$this->transaction($queries);
	}

	public function replaceGroupRoles(int|string $groupId, array $roleIds): void {
		$this->assertAdministrator();
		$groupId = $this->requireId($groupId, 'group');
		$this->validateExistingIds([$groupId], 'base3system_sysgroup', 'group');
		$roleIds = $this->requireIds($roleIds, 'role');
		$this->validateExistingIds($roleIds, 'base3system_sysrole', 'role');
		$queries = [[
			'type' => 'delete',
			'table' => 'base3system_sysgrouprole',
			'where' => $this->eq('base3system_sysgrouprole', 'group_id', $groupId)
		]];

		if (!empty($roleIds)) {
			$values = [];
			foreach ($roleIds as $roleId) {
				$values[] = ['group_id' => $groupId, 'role_id' => $roleId];
			}
			$queries[] = [
				'type' => 'insert',
				'ignore' => true,
				'table' => 'base3system_sysgrouprole',
				'values' => $values
			];
		}

		$this->transaction($queries);
	}

	public function getUserGroups(int|string $userId): array {
		$this->assertAdministrator();
		$userId = $this->requireId($userId, 'user');
		$rows = $this->fetchRows(
			'base3system_sysusergroup',
			['group_id'],
			$this->eq('base3system_sysusergroup', 'user_id', $userId),
			[['element' => $this->fld('base3system_sysusergroup', 'group_id'), 'direction' => 'ASC']]
		);

		return $this->normalizeIds(array_column($rows, 'group_id'));
	}

	public function replaceUserGroups(int|string $userId, array $groupIds): void {
		$this->assertAdministrator();
		$userId = $this->requireId($userId, 'user');
		$this->validateExistingIds([$userId], 'base3system_sysuser', 'user');
		$groupIds = $this->requireIds($groupIds, 'group');
		$this->validateExistingIds($groupIds, 'base3system_sysgroup', 'group');
		$queries = [[
			'type' => 'delete',
			'table' => 'base3system_sysusergroup',
			'where' => $this->eq('base3system_sysusergroup', 'user_id', $userId)
		]];

		if (!empty($groupIds)) {
			$values = [];
			foreach ($groupIds as $groupId) {
				$values[] = ['user_id' => $userId, 'group_id' => $groupId];
			}
			$queries[] = [
				'type' => 'insert',
				'ignore' => true,
				'table' => 'base3system_sysusergroup',
				'values' => $values
			];
		}

		$this->transaction($queries);
	}

	private function roleFields(): array {
		return ['id', 'name', 'label', 'info', 'archive', 'created', 'changed'];
	}

	private function permissionFields(): array {
		return ['id', 'scope', 'permission', 'label', 'info', 'archive', 'created', 'changed'];
	}

	private function withRolePermissions(array $roles, bool $includeArchived): array {
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

			foreach ($this->fetchRows('base3system_syspermission', $this->permissionFields(), $this->and($where)) as $permission) {
				$permissionId = $this->normalizeId($permission['id'] ?? null);
				if ($permissionId !== null) {
					$permissions[$permissionId] = $permission;
				}
			}
		}

		$permissionsByRole = [];
		foreach ($relations as $relation) {
			$roleId = $this->normalizeId($relation['role_id'] ?? null);
			$permissionId = $this->normalizeId($relation['permission_id'] ?? null);
			if ($roleId === null || $permissionId === null || !isset($permissions[$permissionId])) continue;
			$permissionsByRole[$roleId][] = $permissions[$permissionId];
		}

		foreach ($roles as &$role) {
			$roleId = $this->normalizeId($role['id'] ?? null);
			$role['permissions'] = $roleId !== null ? ($permissionsByRole[$roleId] ?? []) : [];
		}
		unset($role);

		return $roles;
	}

	private function containsPermissionPayload(array $data): bool {
		return array_key_exists('permission_ids', $data)
			|| array_key_exists('permissions', $data)
			|| (array_key_exists('scope', $data) && array_key_exists('permission', $data));
	}

	private function resolvePermissionIdsFromPayload(array $data): array {
		if (array_key_exists('permission_ids', $data)) {
			if (!is_array($data['permission_ids'])) throw new \InvalidArgumentException('Invalid permission ID list.');
			return $this->requireIds($data['permission_ids'], 'permission');
		}

		if (array_key_exists('permissions', $data)) {
			if (!is_array($data['permissions']) || !array_is_list($data['permissions'])) throw new \InvalidArgumentException('Invalid permission reference list.');
			$ids = [];
			foreach ($data['permissions'] as $permission) {
				if (is_int($permission) || (is_string($permission) && ctype_digit($permission))) {
					$ids[] = $permission;
					continue;
				}
				if (!is_array($permission)) throw new \InvalidArgumentException('Invalid permission reference.');
				$id = $this->resolvePermissionId($permission);
				if ($id === null) throw new \InvalidArgumentException('Incomplete permission reference.');
				$ids[] = $id;
			}

			return $this->requireIds($ids, 'permission');
		}

		if (array_key_exists('scope', $data) && array_key_exists('permission', $data)) {
			$id = $this->resolvePermissionId($data);
			return $id === null ? [] : [$id];
		}

		return [];
	}

	private function resolvePermissionId(array $permission): ?int {
		$id = $this->normalizeId($permission['id'] ?? null);
		if ($id !== null) {
			return $id;
		}

		if (empty($permission['scope']) || empty($permission['permission'])) {
			return null;
		}

		return $this->normalizeId($this->createPermission($permission));
	}

	/** Invalid input must not silently drop references before a replacement delete. */
	private function requireIds(array $ids, string $name): array {
		$valid = [];
		foreach ($ids as $id) {
			$parsed = $this->normalizeId($id);
			if ($parsed === null) throw new \InvalidArgumentException('Invalid native ' . $name . ' id.');
			$valid[$parsed] = $parsed;
		}
		return array_values($valid);
	}

	/** Validates a complete native ID list before a destructive replacement. */
	private function validateExistingIds(array $ids, string $table, string $name): void {
		if ($ids === []) return;
		$rows = $this->fetchRows($table, ['id'], $this->in($table, 'id', $ids));
		$found = $this->normalizeIds(array_column($rows, 'id'));
		if (count(array_diff($ids, $found)) !== 0) {
			throw new \InvalidArgumentException('Unknown native ' . $name . ' reference.');
		}
	}

	private function requireId(int|string $id, string $name): int {
		$id = $this->normalizeId($id);
		if ($id === null) {
			throw new \InvalidArgumentException('Invalid ' . $name . ' id.');
		}

		return $id;
	}

	private function requireString(string $value, string $name): string {
		$value = trim($value);
		if ($value === '') {
			throw new \InvalidArgumentException('Missing ' . $name . '.');
		}

		return $value;
	}
}
