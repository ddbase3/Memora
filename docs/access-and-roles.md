# Access and roles

Memora uses two complementary authorization layers.

## Entry ACL

Concrete access to individual entries is stored directly for users and groups:

- `base3system_sysuseraccess`
- `base3system_sysgroupaccess`

This layer decides which entries a user can see or edit.

## RBAC

Reusable roles and permissions are stored separately:

- `base3system_sysrole`
- `base3system_syspermission`
- `base3system_sysrolepermission`
- `base3system_sysuserrole`
- `base3system_sysgrouprole`

This layer describes general capabilities such as `entry/admin`, `user/manage`, or `role/manage`.

## Entry admin

A user with `Permission::for('entry', 'admin')` bypasses normal entry filtering. Other users are filtered by direct user/group entry access.

