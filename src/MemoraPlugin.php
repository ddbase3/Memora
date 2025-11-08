<?php declare(strict_types=1);

namespace Memora;

use Base3\Api\ICheck;
use Base3\Api\IClassMap;
use Base3\Api\IContainer;
use Base3\Api\IPlugin;
use DataHawk\Compiler\MysqlReportQueryCompiler;
use DataHawk\Service\DefaultReportQueryService;
use Memora\Api\IMemoraQueryCompiler;
use Memora\Api\IMemoraQueryService;
use Memora\Api\IMemoraQuerySchemaProvider;
use Memora\Query\MemoraQuerySchemaProvider;
use Memora\Service\MemoraEntityDataService;
use ResourceFoundation\Api\IEntityDataService;

class MemoraPlugin implements IPlugin, ICheck {

	public function __construct(private readonly IContainer $container) {}

	// Implementation of IBase

	public static function getName(): string {
		return "memoraplugin";
	}

	// Implementation of IPlugin

	public function init() {
		$this->container

			->set(self::getName(), $this, IContainer::SHARED)

			->set(
				IMemoraQuerySchemaProvider::class,
				fn($c) => new MemoraQuerySchemaProvider,
				IContainer::SHARED)

			->set(
				IMemoraQueryCompiler::class,
				fn($c) => new MysqlReportQueryCompiler(
					$c->get(IMemoraQuerySchemaProvider::class)),
				IContainer::SHARED)

			->set(
				IMemoraQueryService::class,
                                fn($c) => new DefaultReportQueryService(
                                        $c->get(IMemoraQuerySchemaProvider::class),
                                        $c->get(IMemoraQueryCompiler::class),
                                        $c),
				IContainer::SHARED)

			->set(
				IEntityDataService::class,
				fn($c) => new MemoraEntityDataService(
					$c->get(IMemoraQueryService::class),
					$c->get(IClassMap::class)),
				IContainer::SHARED | IContainer::NOOVERWRITE);
	}

	// Implementation of ICheck

	public function checkDependencies() {
		return array(
			'resourcefoundationplugin_installed' => $this->container->get('resourcefoundationplugin') ? 'Ok' : 'resourcefoundationplugin not installed',
			'datahawkplugin_installed' => $this->container->get('datahawkplugin') ? 'Ok' : 'datahawkplugin not installed'
		);
	}
}
