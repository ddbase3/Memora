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
					"element" => [ "type" => "fld", "table" => "base3system_sysentry", "field" => "id" ],
					"alias" => "id"
				],
				[
					"element" => [ "type" => "fld", "table" => "base3system_sysname", "field" => "name" ],
					"alias" => "name"
				],
				[
					"element" => [ "type" => "fld", "table" => "base3system_systype", "field" => "alias" ],
					"alias" => "type_alias"
				]
			],
			"table" => "base3system_sysentry",
			"where" => null,
			"order_by" => [],
		];

		// --- dynamic WHERE conditions ---
		$where = [];

		// Filter by entry ID(s)
		if (!empty($options["entry"])) {
			$ids = is_array($options["entry"]) ? $options["entry"] : [$options["entry"]];
			$where[] = [
				"type" => "op",
				"operator" => "IN",
				"params" => [
					[ "type" => "fld", "table" => "base3system_sysentry", "field" => "id" ],
					$ids
				]
			];
		}

		// Filter by type
		if (!empty($options["type"])) {
			$where[] = [
				"type" => "op",
				"operator" => "=",
				"params" => [
					[ "type" => "fld", "table" => "base3system_systype", "field" => "alias", "variant" => "required" ],
					$options["type"]
				]
			];
		}

		// Filter by tag
		if (!empty($options["tag"])) {
			$tags = is_array($options["tag"]) ? $options["tag"] : [$options["tag"]];
			$where[] = [
				"type" => "op",
				"operator" => "IN",
				"params" => [
					[ "type" => "fld", "table" => "base3system_systag", "field" => "tag", "variant" => "optional" ],
					$tags
				]
			];
		}

		// Combine conditions
		if (count($where) === 1) {
			$query["where"] = $where[0];
		} elseif (count($where) > 1) {
			$query["where"] = [
				"type" => "op",
				"operator" => "AND",
				"params" => $where
			];
		}

		// --- ORDER ---
		$query["order_by"][] = [
			"element" => [ "type" => "fld", "table" => "base3system_sysentry", "field" => "id" ],
			"direction" => $options["orderdir"] ?? "DESC"
		];

		// --- LIMIT ---
		if (!empty($options["limitcount"])) {
			$query["limit"] = (int)$options["limitcount"];
			if (!empty($options["limitoffset"])) {
				$query["offset"] = (int)$options["limitoffset"];
			}
		}

		// --- Execute ---
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
