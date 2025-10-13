<?php declare(strict_types=1);

namespace Memora\Service;

use ResourceFoundation\Api\IEntityDataService;
use DataHawk\Api\IReportQueryService;

class MemoraEntityDataService implements IEntityDataService {

	public function __construct(private readonly IReportQueryService $dataqueryservice) {}

	// Implementation of IEntityDataService

	public function getEntries(array $options = []): array {
		$query = [
			"type" => "select",
			"fields" => [
				[ "element" => [ "type" => "fld", "table" => "base3system_sysentry", "field" => "id" ], "alias" => "id" ],
				[ "element" => [ "type" => "fld", "table" => "base3system_sysentry", "field" => "archive" ], "alias" => "archive" ],
				[ "element" => [ "type" => "fld", "table" => "base3system_sysentry", "field" => "dellock" ], "alias" => "dellock" ],
				[ "element" => [ "type" => "fld", "table" => "base3system_sysentry", "field" => "created" ], "alias" => "created" ],
				[ "element" => [ "type" => "fld", "table" => "base3system_sysentry", "field" => "changed" ], "alias" => "changed" ],
				[ "element" => [ "type" => "fld", "table" => "base3system_systype", "field" => "alias" ], "alias" => "type_alias" ]
			],
			"table" => "base3system_sysentry",
			"where" => null,
			"order_by" => [],
		];

		// Load name
		if (!empty($options["loadname"])) {
			$query["fields"][] = [ "element" => [ "type" => "fld", "table" => "base3system_sysname", "field" => "name" ], "alias" => "name" ];
		}

		// Load tags (as comma-separated or JSON string)
		if (!empty($options["loadtags"])) {
			$query["fields"][] = [
				"element" => [
					"type" => "fn",
					"function" => "GROUP_CONCAT",
					"params" => [
						[ "type" => "fld", "table" => "base3system_systag", "field" => "tag", "variant" => "optional" ]
					]
				],
				"alias" => "tags"
			];
		}

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
			$types = is_array($options["type"]) ? $options["type"] : [$options["type"]];
			$where[] = [
				"type" => "op",
				"operator" => count($types) === 1 ? "=" : "IN",
				"params" => [
					[ "type" => "fld", "table" => "base3system_systype", "field" => "alias", "variant" => "required" ],
					count($types) === 1 ? $types[0] : $types
				]
			];
		}

		// Filter by alloc (AND)
		if (!empty($options["alloc"])) {
			$peers = is_array($options["alloc"]) ? $options["alloc"] : [$options["alloc"]];
			foreach ($peers as $i => $peerId) {
				$where[] = [
					"type" => "op",
					"operator" => "=",
					"params" => [
						[ "type" => "fld", "table" => "base3system_sysallocview", "tablealias" => "alloc" . $i, "field" => "peer_id", "variant" => "optional" ],
						$peerId
					]
				];
			}
		}

		// Filter by inalloc (OR) / excludealloc (NOT)
		foreach (['inalloc' => 'IN', 'excludealloc' => 'NOT IN'] as $key => $operator) {
			if (empty($options[$key])) continue;
			$peers = is_array($options[$key]) ? $options[$key] : [$options[$key]];

			$where[] = [
				"type" => "op",
				"operator" => $operator,
				"params" => [
					[ "type" => "fld", "table" => "base3system_sysallocview", "field" => "peer_id" ],
					$peers
				]
			];
		}

		// Filter by tag (AND)
		if (!empty($options["tag"])) {
			$tags = is_array($options["tag"]) ? $options["tag"] : [$options["tag"]];
			foreach ($tags as $i => $tag) {
				$where[] = [
					"type" => "op",
					"operator" => "=",
					"params" => [
						[
							"type" => "fld",
							"table" => "base3system_systag",
							"tablealias" => "tag" . $i,
							"field" => "tag",
							"variant" => "required"
						],
						$tag
					]
				];
			}
		}

		// Filter by intag (OR) / excludetag (NOT)
		foreach (['intag' => 'IN', 'excludetag' => 'NOT IN'] as $key => $operator) {
			if (empty($options[$key])) continue;
			$tags = is_array($options[$key]) ? $options[$key] : [$options[$key]];

			$where[] = [
				"type" => "op",
				"operator" => $operator,
				"params" => [
					[
						"type" => "fld",
						"table" => "base3system_systag",
						"field" => "tag",
						"variant" => "optional"
					],
					$tags
				]
			];
		}

		// Combine conditions
		if (count($where) === 1) {
			$query["where"] = $where[0];
		} elseif (count($where) > 1) {
			$query["where"] = [ "type" => "op", "operator" => "AND", "params" => $where ];
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

		// --- GROUP BY entry_id if aggregates are used ---
		$needsGrouping = !empty($options["loadtags"]);
		if ($needsGrouping) {
			$query["group_by"] = [
				[ "type" => "fld", "table" => "base3system_sysentry", "field" => "id" ]
			];
		}

		// --- Execute ---
		$result = $this->dataqueryservice->executeQuery($query);
		return $result->rows;
	}

	public function getEntry(int|string $id, array $options = []): ?array {
		$options['entry'] = $id;
		$entries = $this->getEntries($options);
		return $entries[0] ?? null;
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
