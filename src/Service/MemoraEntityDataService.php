<?php declare(strict_types=1);

namespace Memora\Service;

use ResourceApi\Api\IEntityDataService;
use DataHawk\Api\IReportQueryService;

class MemoraEntityDataService implements IEntityDataService {

	public function __construct(private readonly IReportQueryService $dataqueryservice) {}

	// Implementation of IEntityDataService

	public function getEntries(array $options = []): array {

		$query = [
			"type" => "select",
			"fields" => [
				[
					"element" => [
						"type" => "fld",
						"table" => "base3system_sysentry",
						"field" => "id"
					],
					"alias" => "id"
				], [
					"element" => [
						"type" => "fld",
						"table" => "base3system_systype",
						"field" => "alias"
					],
					"alias" => "alias"
				], [
					"element" => [
						"type" => "fld",
						"table" => "base3system_sysname",
						"field" => "name"
					],
					"alias" => "name"
				]
			],
			"table" => "base3system_sysentry",
			"order_by" => [
				[
					"element" => [
						"type" => "fld",
						"table" => "base3system_sysentry",
						"field" => "id"
					],
					"direction" => "desc"
				]
			],
			"limit" => 10
		];

		$result = $this->dataqueryservice->executeQuery($query);

		return $result->rows;
	}

	public function getEntry(int|string $id, array $options = []): ?array {
		// TODO implement
		return null;
	}

	public function saveEntry(array $data): int|string {
		// TODO implement
		return 0;
	}

	public function deleteEntry(int|string $id): bool {
		// TODO implement
		return false;
	}
}
