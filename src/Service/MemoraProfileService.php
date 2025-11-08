<?php declare(strict_types=1);

namespace Memora\Service;

use Base3\Usermanager\Api\IUsermanager;
use Memora\Api\IMemoraProfileService;
use ResourceFoundation\Api\IQueryCompiler;
use ResourceFoundation\Api\IQueryService;

/**
 * Implementierung des Profil-Services für Memora.
 *
 * Nutzt DataHawk (QueryCompiler + ReportQueryService), um auf die
 * Tabelle base3system_sysprofile zuzugreifen.
 */
class MemoraProfileService implements IMemoraProfileService {

	public function __construct(
		private readonly IUsermanager $usermanager,
		private readonly IQueryCompiler $compiler,
		private readonly IQueryService $queryService
	) {}

	/**
	 * {@inheritdoc}
	 */
	public function getActiveProfile(?int $userId = null): ?array {
		$user = $userId ?? ($this->usermanager->getUser()?->id ?? null);
		if (!$user) return null;

		// Query: aktives Profil oder, falls keines aktiv, Standardprofil
		$query = [
			'type' => 'select',
			"table" => "base3system_sysprofile",
			"fields" => [
				["element" => ["type" => "fld", "table" => "base3system_sysprofile", "field" => "id"], "alias" => "id"],
				["element" => ["type" => "fld", "table" => "base3system_sysprofile", "field" => "user_id"], "alias" => "user_id"],
				["element" => ["type" => "fld", "table" => "base3system_sysprofile", "field" => "name"], "alias" => "name"],
				["element" => ["type" => "fld", "table" => "base3system_sysprofile", "field" => "profile"], "alias" => "profile"],
				["element" => ["type" => "fld", "table" => "base3system_sysprofile", "field" => "standard"], "alias" => "standard"],
				["element" => ["type" => "fld", "table" => "base3system_sysprofile", "field" => "protected"], "alias" => "protected"],
				["element" => ["type" => "fld", "table" => "base3system_sysprofile", "field" => "active"], "alias" => "active"],
				["element" => ["type" => "fld", "table" => "base3system_sysprofile", "field" => "archive"], "alias" => "archive"]
			],
			"where" => [[
				"type" => "op",
				"operator" => "AND",
				"params" => [
					[
						"type" => "op",
						"operator" => "=",
						"params" => [
							["type" => "fld", "table" => "base3system_sysprofile", "field" => "user_id"],
							$user
						]
					],
					[
						"type" => "op",
						"operator" => "OR",
						"params" => [
							["type" => "op", "operator" => "=", "params" => [
								["type" => "fld", "table" => "base3system_sysprofile", "field" => "active"], 1
							]],
							["type" => "op", "operator" => "=", "params" => [
								["type" => "fld", "table" => "base3system_sysprofile", "field" => "standard"], 1
							]]
						]
					]
				]
			]],
			"order" => [
				["element" => ["type" => "fld", "table" => "base3system_sysprofile", "field" => "active"], "direction" => "DESC"]
			],
			"limit" => 1
		];

		// $sql = $this->compiler->compile($query);
		$result = $this->queryService->executeQuery($query);
		return $result->rows[0] ?? null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getProfiles(?int $userId = null, bool $includeArchived = false): array {
		$user = $userId ?? ($this->usermanager->getUser()?->id ?? null);
		if (!$user) return [];

		$where = [[
			"type" => "op",
			"operator" => "=",
			"params" => [
				["type" => "fld", "table" => "base3system_sysprofile", "field" => "user_id"],
				$user
			]
		]];

		if (!$includeArchived) {
			$where[] = [
				"type" => "op",
				"operator" => "=",
				"params" => [
					["type" => "fld", "table" => "base3system_sysprofile", "field" => "archive"],
					0
				]
			];
		}

		$query = [
			'type' => 'select',
			"table" => "base3system_sysprofile",
			"fields" => [
				["element" => ["type" => "fld", "table" => "base3system_sysprofile", "field" => "id"], "alias" => "id"],
				["element" => ["type" => "fld", "table" => "base3system_sysprofile", "field" => "name"], "alias" => "name"],
				["element" => ["type" => "fld", "table" => "base3system_sysprofile", "field" => "profile"], "alias" => "profile"],
				["element" => ["type" => "fld", "table" => "base3system_sysprofile", "field" => "active"], "alias" => "active"],
				["element" => ["type" => "fld", "table" => "base3system_sysprofile", "field" => "archive"], "alias" => "archive"]
			],
			"where" => [[
				"type" => "op",
				"operator" => "AND",
				"params" => $where
			]],
			"order" => [
				["element" => ["type" => "fld", "table" => "base3system_sysprofile", "field" => "active"], "direction" => "DESC"],
				["element" => ["type" => "fld", "table" => "base3system_sysprofile", "field" => "name"], "direction" => "ASC"]
			]
		];

		$sql = $this->compiler->compile($query);
		return $this->queryService->executeQuery($sql);
	}

	/**
	 * {@inheritdoc}
	 */
	public function setActiveProfile(int $userId, int $profileId): void {
		// Alle Profile deaktivieren
		$deactivate = [
			"table" => "base3system_sysprofile",
			"type" => "update",
			"set" => [["field" => "active", "value" => 0]],
			"where" => [[
				"type" => "op",
				"operator" => "=",
				"params" => [
					["type" => "fld", "table" => "base3system_sysprofile", "field" => "user_id"],
					$userId
				]
			]]
		];

		$this->queryService->executeQuery($this->compiler->compile($deactivate));

		// Gewähltes Profil aktivieren
		$activate = [
			"table" => "base3system_sysprofile",
			"type" => "update",
			"set" => [["field" => "active", "value" => 1]],
			"where" => [[
				"type" => "op",
				"operator" => "AND",
				"params" => [
					[
						"type" => "op",
						"operator" => "=",
						"params" => [
							["type" => "fld", "table" => "base3system_sysprofile", "field" => "id"],
							$profileId
						]
					],
					[
						"type" => "op",
						"operator" => "=",
						"params" => [
							["type" => "fld", "table" => "base3system_sysprofile", "field" => "user_id"],
							$userId
						]
					]
				]
			]]
		];

		$this->queryService->executeQuery($this->compiler->compile($activate));
	}
}

