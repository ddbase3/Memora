<?php declare(strict_types=1);

namespace Memora\Service;

use Memora\Api\IMemoraQueryService;
use ResourceFoundation\Dto\QueryResult;

abstract class AbstractMemoraTableService {

	public function __construct(
		protected readonly IMemoraQueryService $dataqueryservice
	) {}

	protected function execute(array $query): QueryResult {
		return $this->dataqueryservice->executeQuery($query);
	}

	protected function fetchRows(string $table, array $fields, ?array $where = null, array $order = [], ?int $limit = null): array {
		$query = [
			'type' => 'select',
			'table' => $table,
			'fields' => $this->fields($table, $fields)
		];

		if ($where !== null) {
			$query['where'] = $where;
		}
		if (!empty($order)) {
			$query['order'] = $order;
		}
		if ($limit !== null) {
			$query['limit'] = $limit;
		}

		return $this->execute($query)->rows;
	}

	protected function fetchRow(string $table, array $fields, ?array $where = null, array $order = []): ?array {
		$rows = $this->fetchRows($table, $fields, $where, $order, 1);
		return $rows[0] ?? null;
	}

	protected function insertRow(string $table, array $values, bool $ignore = false): int|string|null {
		$query = [
			'type' => 'insert',
			'table' => $table,
			'values' => [$values]
		];

		if ($ignore) {
			$query['ignore'] = true;
		}

		return $this->execute($query)->insertId;
	}

	protected function insertRows(string $table, array $values, bool $ignore = false): void {
		if (empty($values)) {
			return;
		}

		$query = [
			'type' => 'insert',
			'table' => $table,
			'values' => $values
		];

		if ($ignore) {
			$query['ignore'] = true;
		}

		$this->execute($query);
	}

	protected function updateRows(string $table, array $set, array $where, ?int $limit = null): void {
		if (empty($set)) {
			return;
		}

		$query = [
			'type' => 'update',
			'table' => $table,
			'set' => $set,
			'where' => $where
		];

		if ($limit !== null) {
			$query['limit'] = $limit;
		}

		$this->execute($query);
	}

	protected function deleteRows(string $table, array $where, ?int $limit = null): void {
		$query = [
			'type' => 'delete',
			'table' => $table,
			'where' => $where
		];

		if ($limit !== null) {
			$query['limit'] = $limit;
		}

		$this->execute($query);
	}

	protected function transaction(array $queries): void {
		$queries = array_values(array_filter($queries, static fn($query): bool => is_array($query) && !empty($query)));
		if (empty($queries)) {
			return;
		}

		$this->execute([
			'type' => 'transaction',
			'queries' => $queries
		]);
	}

	protected function fields(string $table, array $fields): array {
		$result = [];

		foreach ($fields as $field) {
			$result[] = [
				'element' => [
					'type' => 'fld',
					'table' => $table,
					'field' => $field
				],
				'alias' => $field
			];
		}

		return $result;
	}

	protected function fld(string $table, string $field): array {
		return [
			'type' => 'fld',
			'table' => $table,
			'field' => $field
		];
	}

	protected function eq(string $table, string $field, mixed $value): array {
		return [
			'type' => 'op',
			'operator' => '=',
			'params' => [
				$this->fld($table, $field),
				$value
			]
		];
	}

	protected function neq(string $table, string $field, mixed $value): array {
		return [
			'type' => 'op',
			'operator' => '!=',
			'params' => [
				$this->fld($table, $field),
				$value
			]
		];
	}

	protected function in(string $table, string $field, array $values): array {
		return [
			'type' => 'op',
			'operator' => 'IN',
			'params' => [
				$this->fld($table, $field),
				array_values($values)
			]
		];
	}

	protected function and(array $conditions): array {
		$conditions = array_values(array_filter($conditions));
		if (count($conditions) === 1) {
			return $conditions[0];
		}

		return [
			'type' => 'op',
			'operator' => 'AND',
			'params' => $conditions
		];
	}

	protected function or(array $conditions): array {
		$conditions = array_values(array_filter($conditions));
		if (count($conditions) === 1) {
			return $conditions[0];
		}

		return [
			'type' => 'op',
			'operator' => 'OR',
			'params' => $conditions
		];
	}

	protected function normalizeId(mixed $value): ?int {
		if (is_string($value) && ctype_digit($value)) {
			$value = (int)$value;
		}

		if (!is_int($value) || $value <= 0) {
			return null;
		}

		return $value;
	}

	protected function normalizeIds(array $values): array {
		$ids = [];

		foreach ($values as $value) {
			$id = $this->normalizeId($value);
			if ($id === null) continue;
			$ids[$id] = $id;
		}

		return array_values($ids);
	}

	protected function normalizeStrings(array $values): array {
		$result = [];

		foreach ($values as $value) {
			$value = trim((string)$value);
			if ($value === '') continue;
			$result[$value] = $value;
		}

		return array_values($result);
	}

	protected function now(): string {
		return date('Y-m-d H:i:s');
	}
}
