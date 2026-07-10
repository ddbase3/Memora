# Memora service layer

Memora exposes ResourceFoundation services for entity data, files, metadata, relations, tags, profile data, activity data, user data, and access administration.

## Access administration

Entry-level access is user/group based:

- `getEntryAccess()`
- `replaceEntryUserAccess()`
- `replaceEntryGroupAccess()`

RBAC administration is separate:

- `getRoles()` / `getRole()` / `createRole()` / `updateRole()` / `archiveRole()`
- `getPermissions()` / `getPermission()` / `createPermission()` / `updatePermission()` / `archivePermission()`
- `getRolePermissions()` / `replaceRolePermissions()`
- `getUserRoles()` / `replaceUserRoles()`
- `getGroupRoles()` / `replaceGroupRoles()`
- `getEffectiveUserRoles()`
- `getUserGroups()` / `replaceUserGroups()`

