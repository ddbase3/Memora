# Memora

**Memora** is a modular XRM/CRM backend for the BASE3 framework and offers a knowledge graph. It provides a unified data layer for managing entities, relationships, metadata, and permissions. Built on **ResourceApi**, it enables flexible data structures and consistent access across different modules and extensions.

---

## Overview

Memora acts as the central data and relationship manager for BASE3-based systems. It abstracts database structures into logical entities and relations that can be queried, connected, and extended dynamically. It is designed for both business and knowledge management scenarios, supporting automation, reporting, and AI-driven workflows.

### Core Responsibilities

* **Entity Management**: Create, read, update, and delete entities of any type.
* **Relations & Hierarchies**: Supports unidirectional, bidirectional, and tree-based connections between entities.
* **Metadata & Tags**: Attach contextual data and tags to any resource.
* **Access Control**: Fine-grained user and group access at entity level.
* **History & Logging**: Built-in change tracking and rating mechanisms.

---

## Architecture

Memora builds on the BASE3 **ResourceApi** to handle storage and relationships, and integrates seamlessly with:

| Layer            | Purpose                                           |
| ---------------- | ------------------------------------------------- |
| **ResourceApi**  | Core entity and file abstraction layer            |
| **ReportApi**    | Data analysis, visualization, and reporting       |
| **AssistentApi** | AI-assisted workflows and automation (MissionBay) |

This modular structure ensures that Memora can act as both a standalone XRM system or as a backend service for other BASE3 plugins.

---

## Example Usage

```php
use ResourcesApi\Api\IEntityDataService;

// get service
$memora = $container->get(IEntityDataService::class);

// Load an entity
$entity = $memora->getEntry(42, ["loadtags" => true]);

// Modify and save
$entity['tags'][] = 'priority';
$memora->saveEntry($entity);
```

---

## Integration

Memora exposes its functionality through multiple interfaces:

* **ResourceApi** for generic data access
* **WebDAV adapters** for file-based access (Nextcloud, ILIAS)
* **MissionBay Nodes** for AI or automation-driven flows
* **ReportApi connectors** for reporting and dashboards

---

## Goals

* Provide a unified XRM data foundation for all BASE3 plugins
* Keep the data model flexible, schema-driven, and self-describing
* Maintain consistency between entities, relations, and metadata
* Enable integration with external storage and service layers

---

## Future Extensions

* Extended role-based access model
* Advanced search and filtering services
* Integration with external CRM/ERP connectors
* AI-assisted relationship discovery via AssistentApi

---

## License

Memora is part of the BASE3 ecosystem and distributed under the same open licensing terms.
