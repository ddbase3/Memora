# Memora service ownership and authorization contracts

Memora implements ResourceFoundation entity data/file/profile/relation/metadata/tag/
structure/activity/user-data APIs through its existing native services. Direct
entry access is owned by `IEntityDataService` and the native Memora query/update
extensions, not by a ResourceFoundation access proxy.

The framework's `IAuthorizationCatalogService` owns native role/permission
catalog CRUD, with the server implementation in `MemoraAuthorizationCatalogService`.
The existing `IAuthorizationAdministrationService` handles direct entry grants,
using `MemoraEntryAuthorizationAdministrationService`; all changes go through
`IEntityDataService::updateEntry()`. `MemoraResourceAuthorizationService` answers
`IAuthorizationService` questions for explicit `xrm` entry resources.

The mixed legacy `IEntityAccessService`, its proxy and microservice have been
retired. Project composition chooses the final services and native authorities.
See `Base3XrmWebsite/docs/resource-microservices.md` and the host
`docs/authorization-xrm-manager.md`.
