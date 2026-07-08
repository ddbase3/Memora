# Memora

**Memora** is a modular XRM/CRM backend and knowledge graph for the BASE3 framework. It provides a unified entity layer for structured data, relations, metadata, tags, access control, and module-based entry semantics. Built on top of **ResourceFoundation** and backed by **DataHawk** query compilation, Memora offers a flexible and extensible CRUD API for domain entities of many different types.

Memora is designed to act as the central data backbone for BASE3-based applications. It can power classic CRM/XRM scenarios, project and contact management, knowledge bases, reporting, workflow automation, and AI-supported applications.

---

## Table of Contents

1. [Overview](#overview)
2. [Core Concepts](#core-concepts)
3. [Architecture](#architecture)
4. [Service Access](#service-access)
5. [Reading Data](#reading-data)
6. [Creating Data](#creating-data)
7. [Updating Data](#updating-data)
8. [Deleting Data](#deleting-data)
9. [Access Control](#access-control)
10. [Tags, Metadata, Typed Data, and Modules](#tags-metadata-typed-data-and-modules)
11. [Relations and Allocations](#relations-and-allocations)
12. [Profiles and Filtering](#profiles-and-filtering)
13. [Extension Architecture](#extension-architecture)
14. [Practical End-to-End Examples](#practical-end-to-end-examples)
15. [Design Notes](#design-notes)
16. [Resource Service Layer](#resource-service-layer)
17. [Role-Based Access](#role-based-access)
18. [Microservice Readiness](#microservice-readiness)
19. [License](#license)

---

## Overview

Memora abstracts a set of relational tables into a consistent entity API.

An entity in Memora consists of:

* a **base entry** in `base3system_sysentry`
* a **type** defined in `base3system_systype`
* an optional **module context** defined in `base3system_sysmodule`
* optional **typed payload data** in a type-specific table
* optional **name** records in `base3system_sysname`
* optional **tags** in `base3system_systag`
* optional **metadata** in `base3system_sysmetadata`
* optional **allocations / relations** in `base3system_sysalloc`
* optional **user access** in `base3system_sysuseraccess`
* optional **group access** in `base3system_sysgroupaccess`
* optional **role access** in `base3system_sysroleaccess`

The goal is to make all of this accessible through a single service:

```php
ResourceFoundation\Api\IEntityDataService
```

This service supports:

* `getEntries(array $options = []): array`
* `getEntry(int|string $id, array $options = []): ?array`
* `createEntry(array $data): int|string`
* `updateEntry(int|string $id, array $patch): int|string`
* `deleteEntry(int|string $id): bool`

---

## Core Concepts

### Entity

A Memora entity is the logical business object you work with. Examples may include:

* projects
* contacts
* companies
* tasks
* notes
* knowledge nodes
* custom domain-specific records

### Type

Every entity has a type alias such as:

* `project`
* `contact`
* `company`

The type resolves to a type-specific payload table through `base3system_systype`.

### Module

A module is a higher-level semantic entry configuration stored in `base3system_sysmodule`.

A module can define:

* the target entry type
* one or more default tags via `base3system_sysmoduletag`

This allows Memora to create or query entries through a more application-oriented concept such as:

* `crmproject`
* `crmcontact`
* `crmnote`

instead of always requiring a raw type.

Important design rule:

* a module is primarily a **creation and filtering convenience**, not an immutable property of the entry
* when an entry is created via `module`, Memora resolves the module to `type` and initial module tags
* after creation, later tag updates may intentionally change the effective module classification of the entry

### Typed Data

Each type may have its own payload table. For example, a `project` type may store fields like:

* `name`
* `description`
* `start`
* `expense`

Memora keeps base entry metadata separate from type-specific business data.

### Tags

Tags are simple string labels used for classification and filtering.

### Metadata

Metadata stores arbitrary named values attached to an entry. These values may be strings or JSON-encoded structured content.

### Allocs

Allocs are undirected relations between entities. Internally they are stored once in `base3system_sysalloc`, while `base3system_sysallocview` exposes both directions for reading.

### Access

Access is defined on entry level and can be granted to:

* users
* groups

This allows fine-grained control over visibility and edit permissions.

---

## Architecture

Memora is composed of several layers.

### BASE3 Layer

BASE3 provides the plugin system, dependency injection container, routing, output handling, and general application infrastructure.

### ResourceFoundation Layer

ResourceFoundation defines the generic CRUD service interfaces and query abstractions used by Memora.

### DataHawk Layer

DataHawk compiles structured query arrays into executable SQL statements. Memora uses this for:

* reading
* inserting
* updating
* deleting
* transactional execution

### Memora Layer

Memora itself adds:

* entity semantics
* type resolution
* module resolution
* relation handling
* metadata handling
* tag handling
* access control integration
* profile-based option enrichment
* extension-driven query and CRUD pipelines

### Extension-Based Execution

Memora is intentionally modular. Querying, creation, and update logic are distributed into extensions.

This has several advantages:

* features remain isolated and testable
* behavior can be extended without rewriting the main service
* domain-specific additions can be added incrementally

---

## Service Access

Memora is typically resolved through dependency injection as a generic entity service.

```php
use ResourceFoundation\Api\IEntityDataService;

$memora = $container->get(IEntityDataService::class);
```

Inside BASE3 display classes this is commonly injected via the constructor.

```php
<?php declare(strict_types=1);

namespace Base3XrmWebsite\Content;

use Base3\Api\IDisplay;
use ResourceFoundation\Api\IEntityDataService;

class ExampleDisplay implements IDisplay {

	public function __construct(private readonly IEntityDataService $entitydataservice) {}

	public static function getName(): string {
		return "exampledisplay";
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		$entry = $this->entitydataservice->getEntry(42, [
			'loadname' => true,
			'loadtags' => true
		]);

		return '<pre>' . htmlspecialchars(print_r($entry, true)) . '</pre>';
	}

	public function getHelp(): string {
		return 'Example display using Memora.';
	}

	public function setData($data) {}
}
```

---

## Reading Data

### `getEntry()`

Loads one entity by ID.

```php
$entry = $memora->getEntry(19384, [
	'loadtype' => true,
	'loadname' => true,
	'loaddata' => true,
	'loadallocs' => true,
	'loadallocuuids' => true,
	'loadtags' => true,
	'loadaccess' => true,
	'loadmetadata' => true
]);
```

### `getEntries()`

Loads a list of entities matching query options.

```php
$entries = $memora->getEntries([
	'type' => 'project',
	'tag' => ['crm'],
	'loadname' => true,
	'loadtags' => true,
	'limit' => 20
]);
```

### Base Fields

The base fields are always part of the entity result:

* `id`
* `uuid`
* `archive`
* `dellock`
* `connections`
* `etag`
* `created`
* `changed`

### Load Verbs

Memora uses explicit load verbs for optional information.

#### `loadtype`

Loads type information.

#### `loadname`

Loads the current name from `base3system_sysname`.

#### `loaddata`

Loads the typed payload from the type-specific table.

#### `loadallocs`

Loads related entry IDs through `base3system_sysallocview`.

#### `loadallocuuids`

Loads related entry UUIDs through `base3system_sysallocview`.

This is useful when consumers need stable external identifiers instead of local numeric entry IDs. The result is exposed as an `allocuuids` array.

`loadallocs` and `loadallocuuids` are intentionally separate:

* `loadallocs` returns local numeric entry IDs
* `loadallocuuids` returns stable UUID strings of the related entries

#### `loadtags`

Loads tag strings.

#### `loadaccess`

Loads effective access for the current user context.

#### `loadmetadata`

Loads metadata into a `metadata` array.

### Filter Verbs

Examples of supported filter semantics include:

* `entry`
* `type`
* `module`
* `tag`
* `intag`
* `excludetag`
* `archive`
* alloc-related filters depending on installed extensions
* ordering / grouping / limit options depending on installed extensions

### Filter by Module

`module` is supported as a first-class query option.

Example:

```php
$entries = $memora->getEntries([
	'module' => 'crmproject',
	'loadname' => true,
	'loaddata' => true,
	'loadtags' => true
]);
```

Semantics:

* a single module resolves to a required type and its configured module tags
* multiple modules are treated as alternative module definitions
* each individual module block is internally translated to a combination of type and tag filters

### Example with Explicit Filters

```php
$entries = $memora->getEntries([
	'type' => 'project',
	'tag' => ['important', 'crm'],
	'excludetag' => ['archived_marker'],
	'archive' => 0,
	'loadname' => true,
	'loaddata' => true,
	'limit' => 50
]);
```

### Example Result

A typical fully-loaded entity may look like this:

```php
[
	'id' => 19384,
	'uuid' => '36cd2ce7cd5f49f4b700d4022f862fe5',
	'archive' => 0,
	'dellock' => 0,
	'connections' => 2,
	'etag' => '6d2f0d6bc1f64a4f94586d8b07a8f6be',
	'created' => '2026-03-06 10:00:00',
	'changed' => '2026-03-06 10:30:00',
	'type' => 'project',
	'name' => 'Project Alpha',
	'tags' => ['crm', 'important'],
	'allocs' => [19385, 19386],
	'allocuuids' => [
		'36cd2ce7cd5f49f4b700d4022f862fe5',
		'9d4f7f5ad2c14c4292e74afc4f24d87c'
	],
	'access' => 'edit',
	'metadata' => [
		'source' => 'import',
		'flags' => [
			'synced' => true
		]
	],
	'data' => [
		'id' => 19384,
		'name' => 'Project Alpha',
		'description' => 'Example project',
		'start' => '2026-03-01',
		'expense' => 1200
	]
]
```

---

## Creating Data

### `createEntry()`

Creates a new entity and returns its ID.

The create payload is extension-based. Depending on the installed create extensions, the following keys are typically supported:

* `type`
* `module`
* `name`
* `data`
* `tags`
* `allocs`
* `useraccess`
* `groupaccess`
* `metadata`

### Create by Type

The classic form uses `type` directly.

```php
$newId = $memora->createEntry([
	'type' => 'project',
	'data' => [
		'name' => 'Minimal Project'
	]
]);
```

### Create by Module

A module may be used instead of a raw type.

```php
$newId = $memora->createEntry([
	'module' => 'crmproject',
	'name' => 'Project Phoenix',
	'tags' => [
		'important',
		'customer_a'
	],
	'data' => [
		'name' => 'Project Phoenix',
		'description' => 'Created through module resolution',
		'start' => date('Y-m-d'),
		'expense' => 0
	]
]);
```

Semantics:

* the module is resolved to its configured type
* the module's configured tags are merged into the create payload
* explicitly provided tags are preserved and deduplicated
* after creation, the entry behaves like a normal entry of the resolved type

### Full Create Example

```php
$newId = $memora->createEntry([
	'type' => 'project',
	'name' => 'Project Phoenix',
	'tags' => [
		'crm',
		'important',
		'customer_a'
	],
	'allocs' => [
		19384,
		19385
	],
	'useraccess' => [
		[
			'user_id' => 2,
			'mode' => 'owner'
		],
		[
			'user_id' => 1,
			'mode' => 'visitor'
		]
	],
	'groupaccess' => [
		[
			'group_id' => 1,
			'mode' => 'visitor'
		]
	],
	'metadata' => [
		'source' => 'manual',
		'flags' => [
			'created_by_test' => true
		]
	],
	'data' => [
		'name' => 'Project Phoenix',
		'description' => 'Created through Memora',
		'start' => date('Y-m-d'),
		'expense' => 0
	]
]);
```

### Create Behavior

During creation Memora typically performs the following steps:

1. resolve module and/or type information
2. normalize payload parts
3. insert base entry
4. queue additional inserts for name, tags, allocs, access, metadata, typed data
5. execute queued statements transactionally
6. return the new entry ID

### Notes

* `uuid` and `etag` are generated automatically if not provided.
* `created` and `changed` are set automatically.
* duplicate tags and duplicate access rows are normalized out before insert.
* typed data is limited to fields that exist in the type table metadata.
* missing tag descriptions are created automatically before tag rows are inserted.

---

## Updating Data

### `updateEntry()`

Updates an existing entity using a **patch syntax**.

Only the explicitly provided operations are applied. Fields not mentioned remain unchanged.

This is a key design rule of Memora: updates are **partial and explicit**.

### Supported Update Verbs

The current flat update syntax uses the following keys.

#### Base Entry

```php
'set' => [
	'archive' => 0,
	'dellock' => 0,
	'connections' => 2
]
```

Supported base fields:

* `archive`
* `dellock`
* `connections`

Fields like `id`, `uuid`, `etag`, and `created` are not patchable through `set`.

#### Name

```php
'setname' => 'New Title'
```

Updates or creates the default language name record (`lang_id = 1`).

#### Typed Data

```php
'setdata' => [
	'description' => 'Updated text',
	'expense' => 500
],
'unsetdata' => [
	'start'
]
```

* `setdata` writes values into the type-specific payload table
* `unsetdata` sets the given typed fields to `NULL`
* if the typed row does not exist yet, it is created when `setdata` contains values

#### Metadata

```php
'setmetadata' => [
	'source' => 'api',
	'flags' => [
		'updated' => true
	]
],
'unsetmetadata' => [
	'temp_key'
]
```

* `setmetadata` inserts or updates metadata keys
* structured values are JSON-encoded automatically
* `unsetmetadata` removes metadata rows by key

#### Tags

```php
'addtags' => ['important'],
'removetags' => ['draft']
```

or:

```php
'replacetags' => ['crm', 'final']
```

Rules:

* `replacetags` must not be combined with `addtags` or `removetags`
* tag values are normalized and deduplicated
* missing tag descriptions are created automatically before new tag rows are inserted
* `replacetags` intentionally may remove module-derived tags from the original create operation

#### Allocs

```php
'addallocs' => [19384, 19385],
'removeallocs' => [19386]
```

or:

```php
'replaceallocs' => [19384, 19385]
```

Rules:

* allocs are undirected
* Memora stores only one canonical relation row in `base3system_sysalloc`
* `replaceallocs` must not be combined with `addallocs` or `removeallocs`

#### User Access

```php
'adduseraccess' => [
	[
		'user_id' => 5,
		'mode' => 'visitor'
	]
],
'removeuseraccess' => [
	[
		'user_id' => 1,
		'mode' => 'visitor'
	]
]
```

or:

```php
'replaceuseraccess' => [
	[
		'user_id' => 2,
		'mode' => 'owner'
	],
	[
		'user_id' => 7,
		'mode' => 'moderator'
	]
]
```

Valid user modes:

* `visitor`
* `moderator`
* `owner`

Removal is always done by complete combination:

* `entry_id`
* `user_id`
* `mode`

#### Group Access

```php
'addgroupaccess' => [
	[
		'group_id' => 1,
		'mode' => 'visitor'
	]
],
'removegroupaccess' => [
	[
		'group_id' => 1,
		'mode' => 'visitor'
	]
]
```

or:

```php
'replacegroupaccess' => [
	[
		'group_id' => 1,
		'mode' => 'moderator'
	]
]
```

Valid group modes:

* `visitor`
* `moderator`

### Full Update Example

```php
$memora->updateEntry(19384, [
	'set' => [
		'archive' => 0,
		'connections' => 3
	],
	'setname' => 'Project Alpha Updated',
	'setdata' => [
		'description' => 'Updated via patch',
		'expense' => 900
	],
	'unsetdata' => [
		'start'
	],
	'addtags' => [
		'updated',
		'priority'
	],
	'removetags' => [
		'draft'
	],
	'addallocs' => [
		19385
	],
	'removeallocs' => [
		19386
	],
	'adduseraccess' => [
		[
			'user_id' => 9,
			'mode' => 'visitor'
		]
	],
	'addgroupaccess' => [
		[
			'group_id' => 4,
			'mode' => 'visitor'
		]
	],
	'setmetadata' => [
		'source' => 'api',
		'flags' => [
			'updated' => true
		]
	],
	'unsetmetadata' => [
		'old_temp_key'
	]
]);
```

### Replace Example

```php
$memora->updateEntry(19384, [
	'replacetags' => ['crm', 'final'],
	'replaceallocs' => [19390, 19391],
	'replaceuseraccess' => [
		[
			'user_id' => 2,
			'mode' => 'owner'
		]
	],
	'replacegroupaccess' => [
		[
			'group_id' => 1,
			'mode' => 'visitor'
		]
	]
]);
```

### Update Behavior

During update Memora typically performs the following steps:

1. load the current entry including access context
2. verify the entry exists
3. verify current user has `edit` access
4. reject update if `dellock` is set
5. normalize and validate patch parts through update extensions
6. queue SQL operations in `transaction_queries`
7. execute all operations in one transaction
8. update the base entry `changed` timestamp

### Error Handling

Updates throw exceptions for invalid situations such as:

* unknown or unsupported patch shape
* invalid field values
* conflicting verbs like `replacetags` + `addtags`
* missing edit access
* missing entry
* invalid type payload updates

---

## Deleting Data

### `deleteEntry()`

Deletes an entity by ID.

```php
$deleted = $memora->deleteEntry(19384);
```

### Delete Behavior

Deletion succeeds only if:

* the entry exists
* current user has `edit` access
* `dellock` is not set

The base entry row is deleted from `base3system_sysentry`. Related rows are expected to be removed through foreign key cascades where configured.

### Example

```php
$id = 19384;

$before = $memora->getEntry($id, [
	'loadname' => true,
	'loaddata' => true,
	'loadtags' => true,
	'loadallocs' => true,
	'loadmetadata' => true
]);

$deleted = $memora->deleteEntry($id);

$after = $memora->getEntry($id, [
	'loadname' => true,
	'loaddata' => true,
	'loadtags' => true,
	'loadallocs' => true,
	'loadmetadata' => true
]);
```

---

## Access Control

Memora supports user-based, group-based, and role-based access.

### User Access Table

`base3system_sysuseraccess`

Fields:

* `entry_id`
* `user_id`
* `mode`

Valid modes:

* `visitor`
* `moderator`
* `owner`

### Group Access Table

`base3system_sysgroupaccess`

Fields:

* `entry_id`
* `group_id`
* `mode`

Valid modes:

* `visitor`
* `moderator`

### Effective Access

When `loadaccess` is used, Memora resolves the current user context into an effective access string such as:

* `edit`
* `view`
* `none`

Admins receive `edit` directly.

### Query Protection

Memora also applies access restrictions at query level through access-related query extensions, so users only see entries they are allowed to access.

Role-based access is described in detail in [Role-Based Access](#role-based-access).

---

## Tags, Metadata, Typed Data, and Modules

### Tags

Tags are lightweight string classifications.

Good use cases:

* labels
* categories
* workflow states
* reporting filters

### Metadata

Metadata is suited for structured context that should not become part of the formal type schema.

Good use cases:

* import markers
* sync states
* integration payloads
* flags
* debugging context
* external IDs

### Typed Data

Typed data belongs to the formal business schema of the entity type.

Good use cases:

* project dates
* contact fields
* financial values
* workflow-specific columns

### Modules

Modules provide a higher-level application entry point for:

* UI creation flows
* module-specific read filters
* default tag enrichment
* type abstraction

They are useful when the application wants to think in business contexts instead of raw schema types.

Example:

* module `crmproject`
* resolves to type `project`
* adds the tag `crm`

### Rule of Thumb

Use:

* **typed data** for business fields that belong to the type schema
* **metadata** for flexible technical or contextual fields
* **tags** for quick categorization and filtering
* **modules** for application-level defaults and initial classification at create/query time

---

## Relations and Allocations

Allocs represent undirected relations between entities.

The physical table is:

* `base3system_sysalloc`

Fields:

* `id`
* `entry_id_1`
* `entry_id_2`

Uniqueness is enforced on:

* `(entry_id_1, entry_id_2)`

For reading, Memora uses the view:

* `base3system_sysallocview`

Fields:

* `id`
* `entry_id`
* `peer_id`

This view mirrors every stored relation in both directions, so a single stored relation can be read naturally from either side.

Example:

If the table stores:

```text
entry_id_1 = 10
entry_id_2 = 20
```

then the view exposes:

```text
entry_id = 10, peer_id = 20
entry_id = 20, peer_id = 10
```

This makes relation reads simple while keeping writes normalized.

### Loading Allocation Identifiers

Memora can expose allocation targets in two different forms.

Use `loadallocs` when the application wants local numeric entry IDs:

```php
$entries = $memora->getEntries([
	'type' => 'project',
	'loadallocs' => true
]);
```

Result:

```php
[
	'id' => 10,
	'allocs' => [20, 30]
]
```

Use `loadallocuuids` when the application needs stable UUID references:

```php
$entries = $memora->getEntries([
	'type' => 'project',
	'loadallocuuids' => true
]);
```

Result:

```php
[
	'id' => 10,
	'uuid' => '36cd2ce7cd5f49f4b700d4022f862fe5',
	'allocuuids' => [
		'9d4f7f5ad2c14c4292e74afc4f24d87c',
		'aa477208c4fb4a8f94db7e0875f92f63'
	]
]
```

This avoids N+1 lookups when exported data or external integrations need UUID-based relations.

---

## Profiles and Filtering

Memora supports profile-based option enrichment through `IMemoraProfileService`.

A profile may transparently append or merge options such as:

* `module`
* `tag`
* `excludetag`
* `excludealloc`
* `archive`

This allows user-specific or context-specific filtering rules without changing each query call manually.

Example idea:

* automatically hide archived entries
* automatically exclude technical tags
* automatically narrow queries to a work context
* automatically bind a user session to a module-specific entry slice

---

## Extension Architecture

Memora is intentionally built around extensions.

### Query Extensions

Interface:

```php
Memora\Api\IMemoraQueryExtension
```

Responsibilities:

* decide if they apply to given query options
* modify the structured query before execution
* post-process result rows after execution

Examples:

* `BaseFieldsExtension`
* `LoadNameExtension`
* `LoadDataExtension`
* `LoadTagsExtension`
* `LoadMetadataExtension`
* `LoadAccessExtension`
* `LoadAllocsExtension`
* `LoadAllocUuidsExtension`
* `FilterByTypeExtension`
* `FilterByTagExtension`
* `FilterByModuleExtension`

### Create Extensions

Interface:

```php
Memora\Api\IMemoraCreateExtension
```

Responsibilities:

* validate and normalize create payload parts
* resolve context such as module and type information
* insert base and related rows
* contribute transaction statements

Examples:

* `CreateModuleResolverCreateExtension`
* `CreateTypeResolverCreateExtension`
* `CreateBaseEntryCreateExtension`
* `CreateNameCreateExtension`
* `CreateTagsCreateExtension`
* `CreateAllocsCreateExtension`
* `CreateUserAccessCreateExtension`
* `CreateGroupAccessCreateExtension`
* `CreateTypedDataCreateExtension`
* `CreateMetadataCreateExtension`

### Update Extensions

Interface:

```php
Memora\Api\IMemoraUpdateExtension
```

Responsibilities:

* validate and normalize patch parts
* add update/insert/delete statements to the transaction queue
* implement partial mutation semantics per domain

Examples:

* `UpdateBaseEntryUpdateExtension`
* `UpdateNameUpdateExtension`
* `UpdateTypedDataUpdateExtension`
* `UpdateMetadataUpdateExtension`
* `UpdateTagsUpdateExtension`
* `UpdateAllocsUpdateExtension`
* `UpdateUserAccessUpdateExtension`
* `UpdateGroupAccessUpdateExtension`
* `UpdateTouchUpdateExtension`

### Benefits of the Extension Model

* clean separation of concerns
* easier debugging and testing
* incremental feature growth
* type/domain-specific customization
* better maintainability than monolithic CRUD logic

---

## Practical End-to-End Examples

### Example: Create and Reload by Type

```php
$newId = $memora->createEntry([
	'type' => 'project',
	'name' => 'Project Delta',
	'tags' => ['crm', 'new'],
	'metadata' => [
		'source' => 'manual'
	],
	'data' => [
		'name' => 'Project Delta',
		'description' => 'Created through README example',
		'expense' => 0
	]
]);

$entry = $memora->getEntry($newId, [
	'loadtype' => true,
	'loadname' => true,
	'loaddata' => true,
	'loadtags' => true,
	'loadmetadata' => true
]);
```

### Example: Create and Reload by Module

```php
$newId = $memora->createEntry([
	'module' => 'crmproject',
	'name' => 'Project Delta',
	'tags' => ['new'],
	'metadata' => [
		'source' => 'manual',
		'module' => 'crmproject'
	],
	'data' => [
		'name' => 'Project Delta',
		'description' => 'Created through module-based README example',
		'expense' => 0
	]
]);

$entry = $memora->getEntry($newId, [
	'loadtype' => true,
	'loadname' => true,
	'loaddata' => true,
	'loadtags' => true,
	'loadmetadata' => true
]);
```

### Example: Query by Module

```php
$entries = $memora->getEntries([
	'module' => 'crmproject',
	'loadname' => true,
	'loaddata' => true,
	'loadtags' => true
]);
```

### Example: Add Metadata and Tags Later

```php
$memora->updateEntry($newId, [
	'addtags' => ['reviewed'],
	'setmetadata' => [
		'qa_status' => 'passed',
		'flags' => [
			'ready' => true
		]
	]
]);
```

### Example: Replace Access Rules

```php
$memora->updateEntry($newId, [
	'replaceuseraccess' => [
		[
			'user_id' => 2,
			'mode' => 'owner'
		],
		[
			'user_id' => 8,
			'mode' => 'moderator'
		]
	],
	'replacegroupaccess' => [
		[
			'group_id' => 1,
			'mode' => 'visitor'
		]
	]
]);
```

### Example: Replace Relations Completely

```php
$memora->updateEntry($newId, [
	'replaceallocs' => [19384, 19385, 19390]
]);
```

### Example: Archive Entry

```php
$memora->updateEntry($newId, [
	'set' => [
		'archive' => 1
	]
]);
```

### Example: Delete Entity

```php
$deleted = $memora->deleteEntry($newId);
```

---

## Design Notes

### Explicitness Over Magic

Memora prefers explicit verbs for loading and patching.

This is why the API uses:

* `loadtags` instead of implicit relation loading
* `setmetadata` instead of silently merging arbitrary top-level keys
* `replacetags` instead of guessing intended semantics from `tags = [...]`
* `module` as an explicit create/query concept instead of hidden application heuristics

### Partial Updates

`updateEntry()` never overwrites unspecified fields.

This is essential for:

* safe API usage
* predictable patch semantics
* composable update operations
* extension-based mutation handling

### Canonical Relation Storage

Undirected relations are stored in one canonical row only. Read symmetry is created through a database view.

### Transactional Mutation

Multi-part create and update operations are executed transactionally so that related changes remain consistent.

### Modules Are Not Immutable

A module-based create operation may initialize an entry with module-derived tags, but later tag updates may intentionally change that classification.

This is by design. Memora treats modules as:

* a creation convenience
* a query convenience
* an application-level semantic layer

but not as a permanently enforced invariant on the stored entry.

---

---

## Resource Service Layer

Memora is not only a table set. It is the concrete BASE3 resource backend for XRM-style entity data.
The public service boundary is intentionally expressed through `ResourceFoundation` interfaces.
This keeps consumer plugins independent from Memora table names, DataHawk query shapes, and create/update extension verbs.

The main rule is:

```text
Consumer code depends on ResourceFoundation interfaces.
Memora provides the concrete implementation.
Project plugins decide the final binding.
```

The default Memora plugin binds the ResourceFoundation service slots to Memora implementations with `IContainer::NOOVERWRITE` where the slot is final-facing and replaceable.
This means Memora can serve as the normal default implementation while a project plugin can deliberately replace a specific service later.

### Service overview

Memora currently provides these ResourceFoundation-backed services:

```text
ResourceFoundation\Api\IEntityDataService      -> Memora\Service\MemoraEntityDataService
ResourceFoundation\Api\IEntityFileService      -> Memora\Service\MemoraEntityFileService
ResourceFoundation\Api\IEntityProfileService   -> Memora\Service\MemoraProfileService
ResourceFoundation\Api\IEntityAccessService    -> Memora\Service\MemoraAccessService
ResourceFoundation\Api\IEntityRelationService  -> Memora\Service\MemoraRelationService
ResourceFoundation\Api\IEntityMetadataService  -> Memora\Service\MemoraMetadataService
ResourceFoundation\Api\IEntityTagService       -> Memora\Service\MemoraTagService
ResourceFoundation\Api\IEntityStructureService -> Memora\Service\MemoraStructureService
ResourceFoundation\Api\IEntityActivityService  -> Memora\Service\MemoraActivityService
ResourceFoundation\Api\IEntityUserDataService  -> Memora\Service\MemoraUserDataService
ResourceFoundation\Api\IFileStorage            -> FileBridge\Local\LocalFileStorage
```

Memora also keeps Memora-specific internal services:

```text
Memora\Api\IMemoraQuerySchemaProvider
Memora\Api\IMemoraQueryCompiler
Memora\Api\IMemoraQueryService
Memora\Api\IMemoraRoleResolver
```

These are implementation details of the Memora backend and should not normally be consumed by reusable feature plugins.

### Removed local profile interface

The profile service no longer uses a Memora-local public profile interface.
There is deliberately no compatibility alias for the old `Memora\Api\IMemoraProfileService`.

Use this instead:

```php
use ResourceFoundation\Api\IEntityProfileService;
```

This is intentional. Profiles are a resource concern, not a Memora-only API concern.

### Internal base service

Most concrete Memora services share structured query and table-write behavior.
That shared behavior lives in:

```text
Memora\Service\AbstractMemoraTableService
```

This class is an internal convenience base class. It centralizes repetitive work such as:

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

It should not be used as a public API. It is there to keep service implementations consistent and small.
Consumer plugins should use ResourceFoundation interfaces instead.

### Entry-aspect services

Several services modify aspects of an entry. These services should normally use the existing `IEntityDataService::updateEntry()` pipeline instead of writing aspect tables manually.

Examples:

```text
MemoraRelationService  -> addallocs, removeallocs, replaceallocs
MemoraMetadataService  -> setmetadata, unsetmetadata
MemoraTagService       -> addtags, removetags, replacetags
MemoraAccessService    -> replaceuseraccess, replacegroupaccess, replaceroleaccess
```

This preserves the existing extension pipeline and keeps behavior consistent with direct calls to `MemoraEntityDataService`.
The extension pipeline remains the single place for transactional mutation, validation, normalization, and permission-sensitive update behavior.

### Direct system-table services

Some services manage Memora system structures that are not normal entry aspects.
Those services may write directly through structured queries:

```text
MemoraProfileService    -> base3system_sysprofile
MemoraAccessService     -> roles, user roles, group roles, user groups
MemoraStructureService  -> types, modules, scopes
MemoraActivityService   -> logs and comments
MemoraUserDataService   -> per-user entry data
```

This split is deliberate:

```text
Entry aspect data       -> go through entry create/update pipelines when possible
Memora control records  -> use direct structured query service methods
```

### Example: relation service

```php
use ResourceFoundation\Api\IEntityRelationService;

final class ProjectRelations {

	public function __construct(
		private readonly IEntityRelationService $relations
	) {}

	public function replaceProjectEntries(int $projectId, array $entryIds): void {
		$this->relations->replaceRelations($projectId, $entryIds);
	}
}
```

The service hides the concrete `sysalloc` representation and delegates to the existing Memora update pipeline.

### Example: metadata service

```php
use ResourceFoundation\Api\IEntityMetadataService;

$metadataService->setMetadata($entryId, [
	'external_id' => 'abc-123',
	'source' => 'import'
]);

$value = $metadataService->getMetadataValue($entryId, 'external_id');
```

### Example: tag service

```php
use ResourceFoundation\Api\IEntityTagService;

$tagService->addEntryTags($entryId, ['crm', 'important']);
$tagService->removeEntryTags($entryId, ['old']);
$tagService->replaceEntryTags($entryId, ['crm', 'customer']);
```

### Example: user data service

```php
use ResourceFoundation\Api\IEntityUserDataService;

$userDataService->setUserData($entryId, [
	'pinned' => true,
	'last_opened_tab' => 'details'
]);
```

User data is intentionally separate from metadata:

```text
metadata       = entry-wide, technical, shared
entryuserdata  = user-specific, personal, UI/state-like
```

---

## Role-Based Access

Memora entry access can now be granted through users, groups, and roles.

The access sources are:

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

### Role semantics

Roles carry their permission semantics directly:

```text
base3system_sysrole.scope
base3system_sysrole.permission
```

For entry access, the expected values are:

```text
scope = entry
permission = view | edit
```

This means the role itself defines what it can do. `sysroleaccess` only connects an entry to a role.

### Why roleaccess does not have a mode field

The user and group access tables use `mode` because their grants are direct entry grants:

```text
useraccess.mode  = visitor | moderator | owner
groupaccess.mode = visitor | moderator
```

Role access works differently:

```text
role.scope       = entry
role.permission  = view | edit
roleaccess       = entry <-> role assignment
```

That avoids mixing two permission models in the same row.

### Owner remains user-entry specific

`owner` remains a direct user-entry mode and is not modeled as a global role.
A user is not generally an owner. A user is owner of a specific entry.

So this remains valid:

```text
base3system_sysuseraccess.mode = owner
```

But this should not be represented as:

```text
base3system_sysrole.permission = owner
```

### Effective access calculation

Effective access is derived from multiple sources.
A user can receive entry access through:

```text
1. direct user access
2. group access through user group membership
3. role access through direct user role membership
4. role access through group role membership
5. admin bypass through the user manager role model
```

The effective result remains:

```text
edit
view
none
```

Typical mapping:

```text
admin                         -> edit
user owner/moderator          -> edit
group moderator               -> edit
role scope=entry permission=edit -> edit
user/group visitor            -> view
role scope=entry permission=view -> view
otherwise                     -> none
```

### Query protection

The query access extension must include the role path when protecting entry queries.
The conceptual condition is:

```text
entry is visible when
  direct user access matches
  OR group access matches
  OR roleaccess points to a role that is assigned to the current user
  OR roleaccess points to a role that is assigned to a group of the current user
```

The role path only counts for entry access when:

```text
sysrole.archive = 0
sysrole.scope = entry
sysrole.permission IN (view, edit)
```

### MemoraRoleResolver

The role resolver is a Memora-internal service:

```text
Memora\Api\IMemoraRoleResolver
Memora\Service\MemoraRoleResolver
```

It centralizes role-related lookup logic:

```text
current user ids
current group ids
direct user role ids
direct group role ids
effective user role ids
effective role rows
filtered role ids by scope and permission
```

This keeps role resolution out of controllers, displays, admin pages, and ad-hoc services.

### MemoraAccessService

The ResourceFoundation-facing access service is:

```text
ResourceFoundation\Api\IEntityAccessService
Memora\Service\MemoraAccessService
```

It manages:

```text
entry user grants
entry group grants
entry role grants
roles
user-role assignments
group-role assignments
user-group assignments
effective user roles
```

Example:

```php
use ResourceFoundation\Api\IEntityAccessService;

$accessService->replaceEntryRoleAccess($entryId, [
	['role_id' => 10],
	['role_id' => 11]
]);
```

Internally this uses the regular Memora update pipeline:

```php
$entityDataService->updateEntry($entryId, [
	'replaceroleaccess' => [
		['role_id' => 10],
		['role_id' => 11]
	]
]);
```

### Entry update verbs for role access

The update pipeline supports:

```text
addroleaccess
removeroleaccess
replaceroleaccess
```

`replaceroleaccess` should not be combined with `addroleaccess` or `removeroleaccess` in the same patch.
This mirrors the existing replace semantics for user access and group access.

### Create payload for role access

Role access can also be included at create time:

```php
$id = $memora->createEntry([
	'type' => 'note',
	'name' => 'Internal note',
	'roleaccess' => [
		['role_id' => 10],
		['role_id' => 11]
	]
]);
```

### Public/default access

The existing public/default model remains valid:

```text
public user id  = 1
default group id = 1
```

Role access extends the model but does not replace public and group defaults.

---

## Microservice Readiness

The service contracts implemented by Memora are designed to be proxied through a project plugin.
The provider runtime can expose local Memora services as microservices, while a consumer runtime can bind ResourceFoundation proxies to those remote endpoints.

Provider-side flow:

```text
remote caller
  -> microservice receiver
  -> Base3XrmWebsite microservice class
  -> ResourceFoundation service interface
  -> Memora service implementation
  -> Memora tables / extension pipeline
```

Consumer-side flow:

```text
consumer plugin
  -> ResourceFoundation proxy
  -> microservice connector
  -> remote Base3XrmWebsite endpoint
  -> Memora implementation on provider side
```

This is why the public service contracts live in ResourceFoundation instead of Memora.
Memora remains the concrete implementation plugin, while ResourceFoundation defines the stable API surface.


## License

Memora is part of the BASE3 ecosystem and distributed under the terms of the **GNU General Public License v3.0 (GPL-3.0)**.
