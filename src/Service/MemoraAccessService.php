<?php declare(strict_types=1);

namespace Memora\Service;

use Memora\Api\IMemoraQueryService;
use Memora\Api\IMemoraRoleResolver;
use ResourceFoundation\Api\IEntityAccessService;
use ResourceFoundation\Api\IEntityDataService;

class MemoraAccessService extends AbstractMemoraTableService implements IEntityAccessService {

	public function __construct(
		IMemoraQueryService $dataqueryservice,
		private readonly IEntityDataService $entitydataservice,
		private readonly IMemoraRoleResolver $roleResolver
	) {
		parent::__construct($dataqueryservice);
	}

	public function getEntryAccess(int|string $entryId): array {
		$entryId = $this->requireId($entryId, 'entry');

		return [
			'useraccess' => $this->fetchRows(
				'base3system_sysuseraccess',
				['id', 'entry_id', 'user_id', 'mode'],
				$this->eq('base3system_sysuseraccess', 'entry_id', $entryId),
				[['element' => $this->fld('base3system_sysuseraccess', 'user_id'), 'direction' => 'ASC']]
			),
			'groupaccess' => $this->fetchRows(
				'base3system_sysgroupaccess',
				['id', 'entry_id', 'group_id', 'mode'],
				$this->eq('base3system_sysgroupaccess', 'entry_id', $entryId),
				[['element' => $this->fld('base3system_sysgroupaccess', 'group_id'), 'direction' => 'ASC']]
			),
			'roleaccess' => $this->fetchRows(
				'base3system_sysroleaccess',
				['id', 'entry_id', 'role_id'],
				$this->eq('base3system_sysroleaccess', 'entry_id', $entryId),
				[['element' => $this->fld('base3system_sysroleaccess', 'role_id'), 'direction' => 'ASC']]
			)
		];
	}

	public function replaceEntryUserAccess(int|string $entryId, array $access): void {
		$this->entitydataservice->updateEntry($entryId, ['replaceuseraccess' => $access]);
	}

	public function replaceEntryGroupAccess(int|string $entryId, array $access): void {
		$this->entitydataservice->updateEntry($entryId, ['replacegroupaccess' => $access]);
	}

	public function replaceEntryRoleAccess(int|string $entryId, array $access): void {
		$this->entitydataservice->updateEntry($entryId, ['replaceroleaccess' => $access]);
	}

	public function getRoles(bool $includeArchived = false): array {
		$where = $includeArchived ? null : $this->eq('base3system_sysrole', 'archive', 0);

		return $this->fetchRows(
			'base3system_sysrole',
			$this->roleFields(),
			$where,
			[
				['element' => $this->fld('base3system_sysrole', 'scope'), 'direction' => 'ASC'],
				['element' => $this->fld('base3system_sysrole', 'name'), 'direction' => 'ASC']
			]
		);
	}

	public function getRole(int|string $roleId): ?array {
		$roleId = $this->requireId($roleId, 'role');
		return $this->fetchRow('base3system_sysrole', $this->roleFields(), $this->eq('base3system_sysrole', 'id', $roleId));
	}

	public function createRole(array $role): int|string {
		$name = $this->requireString((string)($role['name'] ?? ''), 'role name');
		$scope = $this->requireString((string)($role['scope'] ?? ''), 'role scope');
		$permission = $this->requireString((string)($role['permission'] ?? ''), 'role permission');

		$insertId = $this->insertRow('base3system_sysrole', [
			'name' => $name,
			'scope' => $scope,
			'permission' => $permission,
			'label' => array_key_exists('label', $role) ? (string)$role['label'] : null,
			'info' => array_key_exists('info', $role) ? (string)$role['info'] : null,
			'archive' => !empty($role['archive']) ? 1 : 0
		], true);

		if ($insertId) {
			return $insertId;
		}

		$existing = $this->fetchRow('base3system_sysrole', ['id'], $this->eq('base3system_sysrole', 'name', $name));
		return $existing['id'] ?? 0;
	}

	public function updateRole(int|string $roleId, array $patch): void {
		$roleId = $this->requireId($roleId, 'role');
		$set = [];

		foreach (['name', 'scope', 'permission', 'label', 'info'] as $field) {
			if (array_key_exists($field, $patch)) {
				$set[$field] = $patch[$field] === null ? null : (string)$patch[$field];
			}
		}
		if (array_key_exists('archive', $patch)) {
			$set['archive'] = !empty($patch['archive']) ? 1 : 0;
		}

		$this->updateRows('base3system_sysrole', $set, $this->eq('base3system_sysrole', 'id', $roleId), 1);
	}

	public function archiveRole(int|string $roleId): void {
		$this->updateRole($roleId, ['archive' => 1]);
	}

	public function getUserRoles(int|string $userId): array {
		return $this->roleResolver->getRolesByIds($this->roleResolver->getUserRoleIds($userId));
	}

	public function getGroupRoles(int|string $groupId): array {
		return $this->roleResolver->getRolesByIds($this->roleResolver->getGroupRoleIds($groupId));
	}

	public function getEffectiveUserRoles(int|string $userId): array {
		return $this->roleResolver->getEffectiveUserRoles($userId);
	}

	public function replaceUserRoles(int|string $userId, array $roleIds): void {
		$userId = $this->requireId($userId, 'user');
		$roleIds = $this->normalizeIds($roleIds);
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
		$groupId = $this->requireId($groupId, 'group');
		$roleIds = $this->normalizeIds($roleIds);
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
		$userId = $this->requireId($userId, 'user');
		$groupIds = $this->normalizeIds($groupIds);
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
		return ['id', 'name', 'scope', 'permission', 'label', 'info', 'archive', 'created', 'changed'];
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
