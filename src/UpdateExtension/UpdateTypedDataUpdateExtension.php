<?php declare(strict_types=1);

namespace Memora\UpdateExtension;

use Base3\Api\ISortable;
use Memora\Api\IMemoraUpdateExtension;
use Memora\Api\IMemoraQueryService;
use ResourceFoundation\Dto\FieldMetadata;
use ResourceFoundation\Dto\TableMetadata;

class UpdateTypedDataUpdateExtension implements IMemoraUpdateExtension, ISortable {

	public function __construct(private readonly IMemoraQueryService $dataqueryservice) {}

	public function isApplicable(array $patch): bool {
		return !empty($patch['setdata'])
			|| !empty($patch['unsetdata']);
	}

	public function beforeUpdate(array &$patch, array &$context): void {
		if (array_key_exists('setdata', $patch) && !is_array($patch['setdata'])) {
			throw new \InvalidArgumentException("updateEntry patch['setdata'] must be an array.");
		}
		if (array_key_exists('unsetdata', $patch) && !is_array($patch['unsetdata'])) {
			throw new \InvalidArgumentException("updateEntry patch['unsetdata'] must be an array.");
		}

		$this->resolveTypeContext($context);

		$tableName = (string)$context['type_dbtable'];
		$table = $this->dataqueryservice->getTable($tableName);

		if (!$table instanceof TableMetadata) {
			throw new \RuntimeException("Unknown or inaccessible typed data table: " . $tableName);
		}

		$fieldsByName = $this->getFieldsByName($table);
		$allowed = array_keys($fieldsByName);

		if (!in_array('id', $allowed, true)) {
			throw new \RuntimeException("Typed data table '" . $tableName . "' does not contain required primary field 'id'.");
		}

		$set = [];
		foreach (($patch['setdata'] ?? []) as $key => $value) {
			if (!is_string($key)) continue;
			if ($key === 'id') continue;
			if (!isset($fieldsByName[$key])) continue;
			$set[$key] = $this->normalizeTypedValue($value, $fieldsByName[$key]);
		}

		$unset = [];
		foreach (($patch['unsetdata'] ?? []) as $key) {
			if (!is_string($key)) continue;
			$key = trim($key);
			if ($key === '' || $key === 'id') continue;
			if (!isset($fieldsByName[$key])) continue;
			$unset[$key] = true;
		}

		foreach (array_keys($unset) as $key) {
			unset($set[$key]);
		}

		$patch['setdata'] = $set;
		$patch['unsetdata'] = array_keys($unset);
	}

	public function update(array $patch, array &$context): void {
		$entryId = (int)($context['entry_id'] ?? 0);
		$tableName = (string)($context['type_dbtable'] ?? '');

		if ($entryId <= 0 || $tableName === '') {
			throw new \RuntimeException("Missing typed data context in update pipeline.");
		}

		$set = $patch['setdata'] ?? [];
		$unset = $patch['unsetdata'] ?? [];

		if (empty($set) && empty($unset)) {
			return;
		}

		$rowExists = $this->typedDataRowExists($tableName, $entryId);

		if ($rowExists) {
			$updateSet = $set;

			foreach ($unset as $field) {
				$updateSet[$field] = null;
			}

			if (empty($updateSet)) {
				return;
			}

			$context['transaction_queries'][] = [
				'type' => 'update',
				'table' => $tableName,
				'set' => $updateSet,
				'where' => [
					'type' => 'op',
					'operator' => '=',
					'params' => [
						[ 'type' => 'fld', 'table' => $tableName, 'field' => 'id' ],
						$entryId
					]
				],
				'limit' => 1
			];
			return;
		}

		if (empty($set)) {
			return;
		}

		$values = [
			'id' => $entryId
		];

		foreach ($set as $field => $value) {
			$values[$field] = $value;
		}

		$context['transaction_queries'][] = [
			'type' => 'insert',
			'table' => $tableName,
			'values' => [
				$values
			]
		];
	}

	public function afterUpdate(array $patch, array &$context): void {}

	private function resolveTypeContext(array &$context): void {
		if (!empty($context['type_dbtable']) && !empty($context['type_alias']) && !empty($context['type_id'])) {
			return;
		}

		$entryId = (int)($context['entry_id'] ?? 0);
		if ($entryId <= 0) {
			throw new \RuntimeException("Missing context['entry_id'] in update pipeline.");
		}

		$query = [
			'type' => 'select',
			'table' => 'base3system_sysentry',
			'fields' => [
				[ 'element' => [ 'type' => 'fld', 'table' => 'base3system_sysentry', 'field' => 'type_id' ], 'alias' => 'type_id' ],
				[ 'element' => [ 'type' => 'fld', 'table' => 'base3system_systype', 'field' => 'alias' ], 'alias' => 'type_alias' ],
				[ 'element' => [ 'type' => 'fld', 'table' => 'base3system_systype', 'field' => 'dbtable' ], 'alias' => 'type_dbtable' ],
				[ 'element' => [ 'type' => 'fld', 'table' => 'base3system_systype', 'field' => 'primary' ], 'alias' => 'type_primary' ]
			],
			'where' => [
				'type' => 'op',
				'operator' => '=',
				'params' => [
					[ 'type' => 'fld', 'table' => 'base3system_sysentry', 'field' => 'id' ],
					$entryId
				]
			],
			'limit' => 1
		];

		$result = $this->dataqueryservice->executeQuery($query);
		$row = $result->rows[0] ?? null;

		if (!$row) {
			throw new \RuntimeException("Failed to resolve typed data context for entry " . $entryId . ".");
		}

		$context['type_id'] = (int)$row['type_id'];
		$context['type_alias'] = (string)$row['type_alias'];
		$context['type_dbtable'] = (string)$row['type_dbtable'];
		$context['type_primary'] = (string)$row['type_primary'];
	}

	private function typedDataRowExists(string $tableName, int $entryId): bool {
		$query = [
			'type' => 'select',
			'table' => $tableName,
			'fields' => [
				[ 'element' => [ 'type' => 'fld', 'table' => $tableName, 'field' => 'id' ], 'alias' => 'id' ]
			],
			'where' => [
				'type' => 'op',
				'operator' => '=',
				'params' => [
					[ 'type' => 'fld', 'table' => $tableName, 'field' => 'id' ],
					$entryId
				]
			],
			'limit' => 1
		];

		$result = $this->dataqueryservice->executeQuery($query);
		return !empty($result->rows[0]);
	}

	/**
	 * @return array<string, FieldMetadata>
	 */
	private function getFieldsByName(TableMetadata $table): array {
		$fields = [];

		foreach ($table->fields as $field) {
			if ($field instanceof FieldMetadata) {
				$fields[$field->name] = $field;
				continue;
			}
			throw new \RuntimeException("Unexpected field metadata type in table '" . $table->name . "'.");
		}

		return $fields;
	}

	private function normalizeTypedValue(mixed $value, FieldMetadata $field): mixed {
		$type = strtolower(trim($field->type));

		if (($type === 'date' || $type === 'datetime') && is_string($value) && trim($value) === '') {
			return null;
		}

		return $value;
	}

	public function getPriority(): int {
		return 500;
	}
}
