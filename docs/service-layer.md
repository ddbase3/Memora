# Service layer

Memora provides ResourceFoundation service implementations backed by Memora query services.

## Access service

`MemoraAccessService` administers direct entry ACL grants and RBAC role metadata.

Entry ACL operations:

```text
getEntryAccess
replaceEntryUserAccess
replaceEntryGroupAccess
```

RBAC operations:

```text
getRoles
getRole
createRole
updateRole
archiveRole
getPermissions
getPermission
createPermission
updatePermission
archivePermission
getRolePermissions
replaceRolePermissions
getUserRoles
getGroupRoles
getEffectiveUserRoles
replaceUserRoles
replaceGroupRoles
getUserGroups
replaceUserGroups
```

`roleaccess` is retired. Entry-level access remains user/group based until the XRM access model is deliberately redesigned.
