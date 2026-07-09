# Access and roles

Memora separates entry-level ACL grants from reusable RBAC roles.

## Entry ACL

Concrete entry access remains directly attached to users and groups:

```text
base3system_sysuseraccess
base3system_sysgroupaccess
```

These tables decide which concrete entries a user can view or edit. They remain the source of truth for CRM/XRM entry visibility.

## RBAC roles and permissions

Reusable roles and permissions are managed separately:

```text
base3system_sysrole
base3system_syspermission
base3system_sysrolepermission
base3system_sysuserrole
base3system_sysgrouprole
```

A role is a named profile. A permission is an atomic `scope` + `permission` grant. Roles receive permissions through `base3system_sysrolepermission`, and users or groups receive roles through `base3system_sysuserrole` and `base3system_sysgrouprole`.

## Retired role access

`base3system_sysroleaccess` is retired and must not be used for entry ACL. The old `roleaccess`, `addroleaccess`, `removeroleaccess`, and `replaceroleaccess` payload keys are no longer part of the active access API.

## Admin bypass

Entry ACL filtering may be bypassed by users with this RBAC permission:

```text
scope      = entry
permission = admin
```

Code should check it through the Usermanager:

```php
$usermanager->can(Permission::for('entry', 'admin'))
```
