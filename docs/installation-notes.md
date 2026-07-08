# Installation Notes

## Copy package contents

Copy the package contents into the corresponding plugin roots:

```text
ResourceFoundation/* -> plugin/ResourceFoundation/*
Memora/*             -> plugin/Memora/*
```

## Delete obsolete file

Remove the old Memora-local profile interface:

```text
plugin/Memora/src/Api/IMemoraProfileService.php
```

The replacement interface is:

```text
plugin/ResourceFoundation/src/Api/IEntityProfileService.php
```

## Database

This package assumes the role tables already exist:

```text
base3system_sysrole
base3system_sysuserrole
base3system_sysgrouprole
base3system_sysroleaccess
```

No SQL migration is included in this package.

## Smoke checks

After copying files:

```bash
find plugin/ResourceFoundation/src plugin/Memora/src -name '*.php' -print0 | xargs -0 -n1 php -l
```

Then verify container resolution for the new services:

```php
$container->get(ResourceFoundation\Api\IEntityProfileService::class);
$container->get(ResourceFoundation\Api\IEntityAccessService::class);
$container->get(ResourceFoundation\Api\IEntityRelationService::class);
$container->get(ResourceFoundation\Api\IEntityMetadataService::class);
$container->get(ResourceFoundation\Api\IEntityTagService::class);
$container->get(ResourceFoundation\Api\IEntityStructureService::class);
$container->get(ResourceFoundation\Api\IEntityActivityService::class);
$container->get(ResourceFoundation\Api\IEntityUserDataService::class);
```
