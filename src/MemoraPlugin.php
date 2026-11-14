<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of Memora for BASE3 Framework.
 *
 * Memora is a modular XRM/CRM backend and knowledge graph for BASE3.
 * It provides structured entities, relations, metadata and tags.
 * ResourceFoundation contracts govern its shared entity services.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de
 * https://github.com/ddbase3/Memora
 **********************************************************************/

namespace Base3\Memora;

use Base3\CoreFoundation\Api\ICheck;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Api\IContainer;
use Base3\CoreFoundation\Api\IPlugin;
use Base3\CoreFoundation\Configuration\Api\IConfiguration;
use Base3\CoreFoundation\Usermanager\Api\IUsermanager;
use Base3\DataHawk\Compiler\MysqlReportQueryCompiler;
use Base3\DataHawk\Service\DefaultReportQueryService;
use Base3\FileBridge\Local\LocalFileStorage;
use Base3\Memora\Api\IMemoraQueryCompiler;
use Base3\Memora\Api\IMemoraQuerySchemaProvider;
use Base3\Memora\Api\IMemoraQueryService;
use Base3\Memora\Api\IMemoraRoleResolver;
use Base3\Memora\Query\MemoraQuerySchemaProvider;
use Base3\Memora\Service\MemoraAuthorizationCatalogService;
use Base3\Memora\Service\MemoraEntryAuthorizationAdministrationService;
use Base3\Memora\Service\MemoraResourceAuthorizationService;
use Base3\CoreFoundation\Authorization\Api\IAuthorizationCatalogService;
use Base3\CoreFoundation\Authorization\Api\IAuthorizationService;
use Base3\Memora\Service\MemoraActivityService;
use Base3\Memora\Service\MemoraEntityDataService;
use Base3\Memora\Service\MemoraEntityFileService;
use Base3\Memora\Service\MemoraMetadataService;
use Base3\Memora\Service\MemoraProfileService;
use Base3\Memora\Service\MemoraQueryServiceAdapter;
use Base3\Memora\Service\MemoraRelationService;
use Base3\Memora\Service\MemoraRoleResolver;
use Base3\Memora\Service\MemoraStructureService;
use Base3\Memora\Service\MemoraTagService;
use Base3\Memora\Service\MemoraUserDataService;
use Base3\ResourceFoundation\Api\IEntityActivityService;
use Base3\ResourceFoundation\Api\IEntityDataService;
use Base3\ResourceFoundation\Api\IEntityFileService;
use Base3\ResourceFoundation\Api\IEntityMetadataService;
use Base3\ResourceFoundation\Api\IEntityProfileService;
use Base3\ResourceFoundation\Api\IEntityRelationService;
use Base3\ResourceFoundation\Api\IEntityStructureService;
use Base3\ResourceFoundation\Api\IEntityTagService;
use Base3\ResourceFoundation\Api\IEntityUserDataService;
use Base3\ResourceFoundation\Api\IFileStorage;

class MemoraPlugin implements IPlugin, ICheck {

	public function __construct(private readonly IContainer $container) {}

	public static function getName(): string {
		return "memoraplugin";
	}

	public function init() {
		$this->container

			->set(self::getName(), $this, IContainer::SHARED)

			->set(
				IMemoraQuerySchemaProvider::class,
				fn($c) => new MemoraQuerySchemaProvider,
				IContainer::SHARED
			)

			->set(
				IMemoraQueryCompiler::class,
				fn($c) => new MysqlReportQueryCompiler(
					$c->get(IMemoraQuerySchemaProvider::class)
				),
				IContainer::SHARED
			)

			->set(
				IMemoraQueryService::class,
				fn($c) => new MemoraQueryServiceAdapter(
					new DefaultReportQueryService(
						$c->get(IMemoraQuerySchemaProvider::class),
						$c->get(IMemoraQueryCompiler::class),
						$c
					)
				),
				IContainer::SHARED
			)

			->set(
				IMemoraRoleResolver::class,
				fn($c) => new MemoraRoleResolver(
					$c->get(IMemoraQueryService::class),
					$c->get(IUsermanager::class)
				),
				IContainer::SHARED | IContainer::NOOVERWRITE
			)

			->set(
				IEntityProfileService::class,
				fn($c) => new MemoraProfileService(
					$c->get(IMemoraQueryService::class),
					$c->get(IUsermanager::class)
				),
				IContainer::SHARED | IContainer::NOOVERWRITE
			)

			->set(
				IEntityDataService::class,
				fn($c) => new MemoraEntityDataService(
					$c->get(IMemoraQueryService::class),
					$c->get(IClassMap::class),
					$c->get(IEntityProfileService::class)
				),
				IContainer::SHARED | IContainer::NOOVERWRITE
			)

			->set(
				IEntityFileService::class,
				fn($c) => new MemoraEntityFileService(
					$c->get(IEntityDataService::class),
					$c->get(IFileStorage::class)
				),
				IContainer::SHARED | IContainer::NOOVERWRITE
			)

			->set(
				IAuthorizationCatalogService::class,
				fn($c) => new MemoraAuthorizationCatalogService(
					$c->get(IMemoraQueryService::class),
					$c->get(IMemoraRoleResolver::class),
					$c->get(IAuthorizationService::class)
				),
				IContainer::SHARED | IContainer::NOOVERWRITE
			)

			->set(
				MemoraResourceAuthorizationService::class,
				fn($c) => new MemoraResourceAuthorizationService($c->get(IEntityDataService::class)),
				IContainer::SHARED
			)

			->set(
				MemoraEntryAuthorizationAdministrationService::class,
				fn($c) => new MemoraEntryAuthorizationAdministrationService(
					$c->get(IMemoraQueryService::class),
					$c->get(IEntityDataService::class)
				),
				IContainer::SHARED
			)

			->set(
				IEntityRelationService::class,
				fn($c) => new MemoraRelationService(
					$c->get(IMemoraQueryService::class),
					$c->get(IEntityDataService::class)
				),
				IContainer::SHARED | IContainer::NOOVERWRITE
			)

			->set(
				IEntityMetadataService::class,
				fn($c) => new MemoraMetadataService(
					$c->get(IMemoraQueryService::class),
					$c->get(IEntityDataService::class)
				),
				IContainer::SHARED | IContainer::NOOVERWRITE
			)

			->set(
				IEntityTagService::class,
				fn($c) => new MemoraTagService(
					$c->get(IMemoraQueryService::class),
					$c->get(IEntityDataService::class)
				),
				IContainer::SHARED | IContainer::NOOVERWRITE
			)

			->set(
				IEntityStructureService::class,
				fn($c) => new MemoraStructureService(
					$c->get(IMemoraQueryService::class)
				),
				IContainer::SHARED | IContainer::NOOVERWRITE
			)

			->set(
				IEntityActivityService::class,
				fn($c) => new MemoraActivityService(
					$c->get(IMemoraQueryService::class),
					$c->get(IUsermanager::class)
				),
				IContainer::SHARED | IContainer::NOOVERWRITE
			)

			->set(
				IEntityUserDataService::class,
				fn($c) => new MemoraUserDataService(
					$c->get(IMemoraQueryService::class),
					$c->get(IUsermanager::class)
				),
				IContainer::SHARED | IContainer::NOOVERWRITE
			)

			->set(
				IFileStorage::class,
				fn($c) => new LocalFileStorage(
					$c->get(IConfiguration::class)
				),
				IContainer::SHARED | IContainer::NOOVERWRITE
			);
	}

	public function checkDependencies() {
		return array(
			'resourcefoundationplugin_installed' => $this->container->get('resourcefoundationplugin') ? 'Ok' : 'resourcefoundationplugin not installed',
			'datahawkplugin_installed' => $this->container->get('datahawkplugin') ? 'Ok' : 'datahawkplugin not installed'
		);
	}
}
