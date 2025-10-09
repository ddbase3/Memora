<?php declare(strict_types=1);

namespace Memora;

use Base3\Api\ICheck;
use Base3\Api\IContainer;
use Base3\Api\IPlugin;
use DataHawk\Api\IReportQueryService;
use DataHawk\Api\IReportSchemaProvider;
use Memora\DataHawk\MemoraReportSchemaProvider;
use Memora\Service\MemoraEntityDataService;
use ResourceApi\Api\IEntityDataService;

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
			->set(IReportSchemaProvider::class, fn($c) => new MemoraReportSchemaProvider, IContainer::SHARED)
			->set(IEntityDataService::class, fn($c) => new MemoraEntityDataService($c->get(IReportQueryService::class)), IContainer::SHARED);
	}

	// Implementation of ICheck

	public function checkDependencies() {
		return array(
			'resourceapiplugin_installed' => $this->container->get('resourceapiplugin') ? 'Ok' : 'resourceapiplugin not installed',
			'datahawkplugin_installed' => $this->container->get('datahawkplugin') ? 'Ok' : 'datahawkplugin not installed'
		);
	}
}
