# Memora Service Layer

## Purpose

This document describes the Memora service layer after the ResourceFoundation service expansion.

The design goal is to give consuming plugins typed, stable service contracts without forcing them to know Memora table names, DataHawk query shapes, or CRUD extension names.

## Layers

```text
Consumer plugin
  -> ResourceFoundation\Api service interface
    -> Memora\Service implementation
      -> Memora query/create/update pipeline or direct Memora system tables
```

Known services are registered in the container. Discoverable extensions remain in the class map.

## AbstractMemoraTableService

`AbstractMemoraTableService` is an internal convenience base class for Memora services. It centralizes common structured-query boilerplate:

```text
fetchRows()
fetchRow()
insertRow()
insertRows()
updateRows()
deleteRows()
transaction()
fields()
fld()
eq()
neq()
in()
and()
or()
normalizeId()
normalizeIds()
normalizeStrings()
```

It is intentionally internal to Memora. It is not a ResourceFoundation contract and should not be used by consumer plugins.

## Entry-aspect services

These services modify entries through `IEntityDataService::updateEntry()` when possible:

```text
MemoraRelationService   -> addallocs/removeallocs/replaceallocs
MemoraMetadataService   -> setmetadata/unsetmetadata
MemoraTagService        -> addtags/removetags/replacetags
MemoraAccessService     -> replaceuseraccess/replacegroupaccess/replaceroleaccess
```

This preserves the existing Memora update pipeline, permission checks, transaction handling, and normalization logic.

## Direct system-table services

These services write directly because their data is not a normal entry update:

```text
MemoraProfileService
MemoraAccessService    roles, user roles, group roles, user groups
MemoraStructureService types, modules, scopes
MemoraActivityService  logs, comments
MemoraUserDataService  per-user entity data
```

## Migration path

Consumer code should migrate from Memora-local APIs to ResourceFoundation APIs.

Old:

```php
use Memora\Api\IMemoraProfileService;
```

New:

```php
use ResourceFoundation\Api\IEntityProfileService;
```

There is no legacy alias in this package by design.
