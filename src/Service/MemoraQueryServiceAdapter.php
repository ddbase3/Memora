<?php declare(strict_types=1);

namespace Memora\Service;

use Memora\Api\IMemoraQueryService;
use ResourceFoundation\Api\IQueryService;
use ResourceFoundation\Dto\QueryResult;
use ResourceFoundation\Dto\TableMetadata;

class MemoraQueryServiceAdapter implements IMemoraQueryService {

	public function __construct(
		private readonly IQueryService $inner
	) {}

	/**
	 * @return TableMetadata[]
	 */
	public function listTables(): array {
		return $this->inner->listTables();
	}

	public function getTable(string $tableName): ?TableMetadata {
		return $this->inner->getTable($tableName);
	}

	public function executeQuery(array $queryJson): QueryResult {
		return $this->inner->executeQuery($queryJson);
	}

	/**
	 * @return string[]
	 */
	public function listDomains(): array {
		return $this->inner->listDomains();
	}

	/**
	 * @return string[]
	 */
	public function listCategories(): array {
		return $this->inner->listCategories();
	}

	/**
	 * @return string[]
	 */
	public function listTags(): array {
		return $this->inner->listTags();
	}
}
