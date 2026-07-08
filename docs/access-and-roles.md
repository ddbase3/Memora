# Access and Roles

## Tables

Memora entry access can be granted through:

```text
base3system_sysuseraccess
base3system_sysgroupaccess
base3system_sysroleaccess
```

Roles and role membership are stored in:

```text
base3system_sysrole
base3system_sysuserrole
base3system_sysgrouprole
base3system_sysusergroup
```

## Role semantics

Roles carry their permission semantics directly:

```text
sysrole.scope
sysrole.permission
```

For entry access, the expected values are:

```text
scope = entry
permission = view | edit
```

`owner` remains an entry-user access mode and is not modeled as a global role.

## MemoraRoleResolver

`MemoraRoleResolver` resolves role memberships for users and groups.

It can provide:

```text
current user ids
current group ids
direct user role ids
direct group role ids
effective user role ids
effective role rows
filtered role ids by scope and permission
```

This keeps role-resolution SQL out of controllers and admin displays.

## MemoraAccessService

`MemoraAccessService` is the public ResourceFoundation-level access service implementation. It handles:

```text
entry user grants
entry group grants
entry role grants
role CRUD
user-role replacement
group-role replacement
user-group replacement
effective user roles
```

Entry grant replacement uses the existing Memora update pipeline:

```php
$accessService->replaceEntryRoleAccess($entryId, [
	['role_id' => 10],
	['role_id' => 11]
]);
```

which internally calls:

```php
$entityDataService->updateEntry($entryId, [
	'replaceroleaccess' => [...]
]);
```

## Public and default access

The existing public/default access model remains valid:

```text
public user id: 1
default group id: 1
```

These constants are still used by the query/load access extensions.
