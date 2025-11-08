<?php declare(strict_types=1);

namespace Memora\Query;

use Base3\Api\IContainer;
use DataHawk\Api\IReportQueryCompiler;
use DataHawk\Api\IReportSchemaProvider;
use DataHawk\Dto\QueryResult;
use DataHawk\Service\DefaultReportQueryService;
use Memora\Api\IMemoraReportQueryService;

class MemoraReportQueryService extends DefaultReportQueryService implements IMemoraReportQueryService {

	public function __construct(
		private IReportSchemaProvider $schemaProvider,
		private IReportQueryCompiler $querycompiler,
		private IContainer $container
	) {
		parent::__construct($schemaProvider, $querycompiler, $container);
	}

	public function executeQuery(array $queryJson): QueryResult {
		return parent::executeQuery($queryJson);
	}
}
