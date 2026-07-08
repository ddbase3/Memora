<?php declare(strict_types=1);

namespace Memora;

use Base3\Api\ICheck;
use Base3\Api\IClassMap;
use Base3\Api\IContainer;
use Base3\Api\IPlugin;
use Base3\Configuration\Api\IConfiguration;
use Base3\Usermanager\Api\IUsermanager;
use DataHawk\Compiler\MysqlReportQueryCompiler;
use DataHawk\Service\DefaultReportQueryService;
use FileBridge\Local\LocalFileStorage;
use Memora\Api\IMemoraQueryCompiler;
use Memora\Api\IMemoraQuerySchemaProvider;
use Memora\Api\IMemoraQueryService;
use Memora\Api\IMemoraRoleResolver;
use Memora\Query\MemoraQuerySchemaProvider;
use Memora\Service\MemoraAccessService;
use Memora\Service\MemoraActivityService;
use Memora\Service\MemoraEntityDataService;
use Memora\Service\MemoraEntityFileService;
use Memora\Service\MemoraMetadataService;
use Memora\Service\MemoraProfileService;
use Memora\Service\MemoraQueryServiceAdapter;
use Memora\Service\MemoraRelationService;
use Memora\Service\MemoraRoleResolver;
use Memora\Service\MemoraStructureService;
use Memora\Service\MemoraTagService;
use Memora\Service\MemoraUserDataService;
use ResourceFoundation\Api\IEntityAccessService;
use ResourceFoundation\Api\IEntityActivityService;
use ResourceFoundation\Api\IEntityDataService;
use ResourceFoundation\Api\IEntityFileService;
use ResourceFoundation\Api\IEntityMetadataService;
use ResourceFoundation\Api\IEntityProfileService;
use ResourceFoundation\Api\IEntityRelationService;
use ResourceFoundation\Api\IEntityStructureService;
use ResourceFoundation\Api\IEntityTagService;
use ResourceFoundation\Api\IEntityUserDataService;
use ResourceFoundation\Api\IFileStorage;

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
				IContainer::SHARED
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
				IEntityAccessService::class,
				fn($c) => new MemoraAccessService(
					$c->get(IMemoraQueryService::class),
					$c->get(IEntityDataService::class),
					$c->get(IMemoraRoleResolver::class)
				),
				IContainer::SHARED | IContainer::NOOVERWRITE
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
