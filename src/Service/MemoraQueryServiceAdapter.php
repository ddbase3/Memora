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

namespace Base3\Memora\Service;

use Base3\Memora\Api\IMemoraQueryService;
use Base3\ResourceFoundation\Api\IQueryService;
use Base3\ResourceFoundation\Dto\QueryResult;
use Base3\ResourceFoundation\Dto\TableMetadata;

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
