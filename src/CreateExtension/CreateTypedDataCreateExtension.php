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

namespace Base3\Memora\CreateExtension;

use Base3\CoreFoundation\Api\ISortable;
use Base3\Memora\Api\IMemoraCreateExtension;
use Base3\Memora\Api\IMemoraQueryService;
use Base3\ResourceFoundation\Dto\FieldMetadata;
use Base3\ResourceFoundation\Dto\TableMetadata;

class CreateTypedDataCreateExtension implements IMemoraCreateExtension, ISortable {

	public function __construct(private readonly IMemoraQueryService $dataqueryservice) {}

	public function isApplicable(array $entry): bool {
		return !empty($entry['data']) && is_array($entry['data']);
	}

	public function beforeCreate(array &$entry, array &$context): void {}

	public function create(array $entry, array &$context): void {
		if (empty($context['entry_id'])) {
			throw new \RuntimeException("Missing context['entry_id']. Ensure CreateBaseEntryCreateExtension ran first.");
		}
		if (empty($context['type_dbtable']) || !is_string($context['type_dbtable'])) {
			throw new \RuntimeException("Missing context['type_dbtable']. Ensure CreateTypeResolverCreateExtension ran first.");
		}

		$data = $entry['data'] ?? null;
		if (!is_array($data) || empty($data)) return;

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

		$values = [
			'id' => (int)$context['entry_id']
		];

		foreach ($data as $key => $val) {
			if (!is_string($key)) continue;
			if ($key === 'id') continue;
			if (!isset($fieldsByName[$key])) continue;

			$values[$key] = $this->normalizeTypedValue($val, $fieldsByName[$key]);
		}

		if (count($values) <= 1) return;

		$context['transaction_queries'][] = [
			'type' => 'insert',
			'table' => $tableName,
			'values' => [ $values ]
		];
	}

	public function afterCreate(array $entry, array &$context): void {}

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
		return 700;
	}
}
