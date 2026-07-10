# Installation notes

Memora expects the standard XRM system tables for entries, users, groups, direct entry access, roles, permissions, and RBAC assignment tables.

Required access tables:

- `base3system_sysuseraccess`
- `base3system_sysgroupaccess`
- `base3system_sysrole`
- `base3system_syspermission`
- `base3system_sysrolepermission`
- `base3system_sysuserrole`
- `base3system_sysgrouprole`

After updating plugin files, clear any class-map, opcode, or application caches used by the installation, then log out and log in again so user roles and permissions are loaded fresh.

