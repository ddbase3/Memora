<?php declare(strict_types=1);

namespace Memora\CreateExtension;

use Base3\Api\ISortable;
use Memora\Api\IMemoraCreateExtension;
use Memora\Api\IMemoraQueryService;
use ResourceFoundation\Dto\FieldMetadata;
use ResourceFoundation\Dto\TableMetadata;

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

		$allowed = $this->getAllowedFieldNames($table);
		if (!in_array('id', $allowed, true)) {
			throw new \RuntimeException("Typed data table '" . $tableName . "' does not contain required primary field 'id'.");
		}

		$values = [
			'id' => (int)$context['entry_id']
		];

		foreach ($data as $key => $val) {
			if (!is_string($key)) continue;
			if ($key === 'id') continue;
			if (!in_array($key, $allowed, true)) continue;

			$values[$key] = $val;
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
	 * @return string[]
	 */
	private function getAllowedFieldNames(TableMetadata $table): array {
		$names = [];

		foreach ($table->fields as $field) {
			if ($field instanceof FieldMetadata) {
				$names[] = $field->name;
				continue;
			}
			throw new \RuntimeException("Unexpected field metadata type in table '" . $table->name . "'.");
		}

		return $names;
	}

	public function getPriority(): int {
		return 700;
	}
}
