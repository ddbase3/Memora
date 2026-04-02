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
use Memora\Api\IMemoraProfileService;
use Memora\Api\IMemoraQueryCompiler;
use Memora\Api\IMemoraQueryService;
use Memora\Api\IMemoraQuerySchemaProvider;
use Memora\Query\MemoraQuerySchemaProvider;
use Memora\Service\MemoraEntityDataService;
use Memora\Service\MemoraEntityFileService;
use Memora\Service\MemoraProfileService;
use Memora\Service\MemoraQueryServiceAdapter;
use ResourceFoundation\Api\IEntityDataService;
use ResourceFoundation\Api\IEntityFileService;
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
				IMemoraProfileService::class,
				fn($c) => new MemoraProfileService(
					$c->get(IUsermanager::class),
					$c->get(IMemoraQueryCompiler::class),
					$c->get(IMemoraQueryService::class)
				),
				IContainer::SHARED
			)

			->set(
				IEntityDataService::class,
				fn($c) => new MemoraEntityDataService(
					$c->get(IMemoraQueryService::class),
					$c->get(IClassMap::class),
					$c->get(IMemoraProfileService::class)
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
