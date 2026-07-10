# Memora

Memora provides the XRM data backend and service layer for BASE3 resource entities.

## Access model

Entry-level access remains directly attached to users and groups:

- `base3system_sysuseraccess`
- `base3system_sysgroupaccess`

General reusable authorization is handled through RBAC:

- `base3system_sysrole`
- `base3system_syspermission`
- `base3system_sysrolepermission`
- `base3system_sysuserrole`
- `base3system_sysgrouprole`

The two models are intentionally separate. Roles and permissions describe general capabilities, while entry access describes concrete access grants for individual entries.

## Admin bypass

Entry access filtering is bypassed when the current user has:

```php
Permission::for('entry', 'admin')
```

Otherwise entry filtering uses direct user and group access rows.

## Service layer

`MemoraAccessService` implements `ResourceFoundation\Api\IEntityAccessService` and supports:

- reading and replacing entry user access
- reading and replacing entry group access
- managing roles
- managing permissions
- assigning permissions to roles
- assigning roles to users and groups
- reading effective user roles

