<?php declare(strict_types=1);

namespace Memora;

use Base3\Api\ICheck;
use Base3\Api\IClassMap;
use Base3\Api\IContainer;
use Base3\Api\IPlugin;
use Memora\Api\IMemoraReportQueryCompiler;
use Memora\Api\IMemoraReportQueryService;
use Memora\Api\IMemoraReportSchemaProvider;
use Memora\Query\MemoraReportQueryCompiler;
use Memora\Query\MemoraReportQueryService;
use Memora\Query\MemoraReportSchemaProvider;
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
				IMemoraReportSchemaProvider::class,
				fn($c) => new MemoraReportSchemaProvider,
				IContainer::SHARED)

			->set(
				IMemoraReportQueryCompiler::class,
				fn($c) => new MemoraReportQueryCompiler(
					$c->get(IMemoraReportSchemaProvider::class)),
				IContainer::SHARED)

			->set(
				IMemoraReportQueryService::class,
                                fn($c) => new MemoraReportQueryService(
                                        $c->get(IMemoraReportSchemaProvider::class),
                                        $c->get(IMemoraReportQueryCompiler::class),
                                        $c),
				IContainer::SHARED)

			->set(
				IEntityDataService::class,
				fn($c) => new MemoraEntityDataService(
					$c->get(IMemoraReportQueryService::class),
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
