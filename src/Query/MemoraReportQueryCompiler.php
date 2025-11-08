<?php declare(strict_types=1);

namespace Memora\Query;

use DataHawk\Compiler\MysqlReportQueryCompiler;
use Memora\Api\IMemoraReportQueryCompiler;
use Memora\Api\IMemoraReportSchemaProvider;

class MemoraReportQueryCompiler extends MysqlReportQueryCompiler implements IMemoraReportQueryCompiler {

	public function __construct(IMemoraReportSchemaProvider $schemaProvider) {
		parent::__construct($schemaProvider);
	}
}
