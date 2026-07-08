# ResourceFoundation Contracts Implemented by Memora

Memora implements ResourceFoundation contracts so feature plugins can depend on stable foundation APIs.

## Contracts

```text
IEntityDataService
IEntityFileService
IEntityProfileService
IEntityAccessService
IEntityRelationService
IEntityMetadataService
IEntityTagService
IEntityStructureService
IEntityActivityService
IEntityUserDataService
```

## Microservice readiness

ResourceFoundation includes proxy classes for these contracts. A project plugin can later bind a proxy instead of a local implementation:

```php
use ResourceFoundation\Api\IEntityAccessService;
use ResourceFoundation\Proxy\EntityAccessProxy;

$container->set(
	IEntityAccessService::class,
	fn($c) => new EntityAccessProxy(
		$c->get('microservicehelper')->get('Xrm', IEntityAccessService::class, 'entityaccessmicroservice')
	),
	IContainer::SHARED
);
```

This mirrors the existing `TokenProxy` and `UsermanagerProxy` pattern in project plugins.

## Service replacement

Memora registers ResourceFoundation services with `IContainer::NOOVERWRITE` where they are final-facing resource slots. Project plugins may replace them deliberately.

Memora-internal services such as `IMemoraQueryService` and `IMemoraRoleResolver` remain Memora-specific.
