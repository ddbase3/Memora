<?php declare(strict_types=1);

namespace Memora\Query;

use Memora\Api\IMemoraQuerySchemaProvider;
use ResourceFoundation\Dto\FieldMetadata;
use ResourceFoundation\Dto\ForeignKeyReference;
use ResourceFoundation\Dto\JoinMetadata;
use ResourceFoundation\Dto\TableMetadata;

class MemoraQuerySchemaProvider implements IMemoraQuerySchemaProvider {

	private const RETIRED_TABLES = [
		'base3system_sysroleaccess'
	];

	private string $schemaDir;

	public function __construct() {
		$this->schemaDir = rtrim(DIR_PLUGIN, '/\\') . '/Memora/local/Schema';
	}

	public function getSchema(): array {
		$tables = [];
		foreach (glob($this->schemaDir . '/*.json') as $file) {
			$table = $this->loadTableFromFile($file);
			if ($table) $tables[] = $table;
		}
		return $tables;
	}

	public function getTable(string $tableName): ?TableMetadata {
		if (in_array($tableName, self::RETIRED_TABLES, true)) return null;

		$shortName = $this->shortenTableName($tableName);
		$file = $this->schemaDir . '/' . $shortName . '.json';
		if (!is_file($file)) return null;
		return $this->loadTableFromFile($file);
	}

	private function loadTableFromFile(string $file): ?TableMetadata {
		$json = file_get_contents($file);
		if ($json === false || $json === '') return null;

		$data = json_decode($json, true);
		if (!is_array($data) || empty($data['name']) || !is_string($data['name'])) return null;
		if (in_array($data['name'], self::RETIRED_TABLES, true)) return null;

		$fields = [];
		foreach (($data['fields'] ?? []) as $f) {
			if (!is_array($f) || empty($f['name']) || !is_string($f['name'])) continue;

			$name = $f['name'];
			$type = is_string($f['type'] ?? null) ? $f['type'] : 'string';

			// We map "required" to nullable=false; if not required, nullable=true.
			$required = (bool)($f['required'] ?? false);
			$nullable = !$required;

			$description = null;
			if (is_string($f['description'] ?? null)) $description = $f['description'];
			elseif (is_string($f['label'] ?? null)) $description = $f['label'];

			$fields[] = new FieldMetadata(
				name: $name,
				type: $type,
				description: $description,
				primaryKey: false,
				foreignKey: null,
				nullable: $nullable,
				tags: is_array($f['tags'] ?? null) ? $f['tags'] : [],
				alias: is_string($f['alias'] ?? null) ? $f['alias'] : null,
				sensitive: (bool)($f['sensitive'] ?? false)
			);
		}

		$joins = [];
		foreach (($data['joins'] ?? []) as $j) {
			if (!is_array($j) || empty($j['targetTable']) || !is_string($j['targetTable'])) continue;

			$joins[] = new JoinMetadata(
				targetTable: $j['targetTable'],
				on: is_array($j['on'] ?? null) ? $j['on'] : [],
				type: is_string($j['type'] ?? null) ? $j['type'] : 'LEFT',
				meta: is_array($j['meta'] ?? null) ? $j['meta'] : []
			);
		}

		return new TableMetadata(
			name: $data['name'],
			label: is_string($data['label'] ?? null) ? $data['label'] : $data['name'],
			description: is_string($data['description'] ?? null) ? $data['description'] : null,
			domain: is_string($data['domain'] ?? null) ? $data['domain'] : '',
			category: is_string($data['category'] ?? null) ? $data['category'] : '',
			tags: is_array($data['tags'] ?? null) ? $data['tags'] : [],
			fields: $fields,
			joins: $joins,
			defaultFilters: is_array($data['defaultFilters'] ?? null) ? $data['defaultFilters'] : [],
			sensitive: (bool)($data['sensitive'] ?? false),
			position: is_array($data['position'] ?? null) ? $data['position'] : []
		);
	}

	private function shortenTableName(string $tableName): string {
		return (string)preg_replace('/^base3system_/', '', $tableName);
	}
}
