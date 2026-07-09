<?php declare(strict_types=1);

namespace Memora\Api;

/**
 * Resolves Memora role memberships.
 *
 * This is a Memora-internal helper contract. Resource consumers should normally
 * use ResourceFoundation\Api\IEntityAccessService for role and permission
 * administration.
 */
interface IMemoraRoleResolver {

	/** @return array<int,int> User identifiers relevant for the current access context. */
	public function getCurrentUserIds(bool $includePublic = true): array;

	/** @return array<int,int> Group identifiers relevant for the current access context. */
	public function getCurrentGroupIds(bool $includeDefault = true): array;

	/** @return array<int,int> Role identifiers assigned directly to a user. */
	public function getUserRoleIds(int|string $userId, bool $includeArchived = false): array;

	/** @return array<int,int> Role identifiers assigned directly to a group. */
	public function getGroupRoleIds(int|string $groupId, bool $includeArchived = false): array;

	/** @return array<int,int> Effective role identifiers for a user, including group roles. */
	public function getEffectiveUserRoleIds(int|string $userId, bool $includeArchived = false): array;

	/** @return array<int,array<string,mixed>> Effective role rows for a user, including group roles and permissions. */
	public function getEffectiveUserRoles(int|string $userId, bool $includeArchived = false): array;

	/**
	 * Returns role identifiers for the given user/group membership sets.
	 *
	 * @param array<int,int|string> $userIds User identifiers
	 * @param array<int,int|string> $groupIds Group identifiers
	 * @param string|null $scope Optional permission scope filter
	 * @param array<int,string> $permissions Optional permission-name filter
	 * @param bool $includeArchived Whether archived roles should be included
	 * @return array<int,int> Role identifiers
	 */
	public function getRoleIdsForUsersAndGroups(array $userIds, array $groupIds, ?string $scope = null, array $permissions = [], bool $includeArchived = false): array;

	/**
	 * Returns role rows for the given role identifiers, including assigned permissions.
	 *
	 * @param array<int,int|string> $roleIds Role identifiers
	 * @param bool $includeArchived Whether archived roles should be included
	 * @return array<int,array<string,mixed>> Role rows
	 */
	public function getRolesByIds(array $roleIds, bool $includeArchived = false): array;
}
