<?php declare(strict_types=1);

namespace Memora\Service;

use Memora\Api\IMemoraQueryService;
use ResourceFoundation\Api\IEntityStructureService;

class MemoraStructureService extends AbstractMemoraTableService implements IEntityStructureService {

	public function getTypes(): array {
		return $this->fetchRows(
			'base3system_systype',
			['id', 'alias', 'dbtable', 'primary'],
			null,
			[['element' => $this->fld('base3system_systype', 'alias'), 'direction' => 'ASC']]
		);
	}

	public function getType(int|string $type): ?array {
		if (is_string($type) && !ctype_digit($type)) {
			$type = trim($type);
			if ($type === '') return null;
			return $this->fetchRow('base3system_systype', ['id', 'alias', 'dbtable', 'primary'], $this->eq('base3system_systype', 'alias', $type));
		}

		$typeId = $this->normalizeId($type);
		if ($typeId === null) {
			return null;
		}

		return $this->fetchRow('base3system_systype', ['id', 'alias', 'dbtable', 'primary'], $this->eq('base3system_systype', 'id', $typeId));
	}

	public function createType(array $type): int|string {
		$alias = $this->requireString((string)($type['alias'] ?? ''), 'type alias');
		$dbtable = $this->requireString((string)($type['dbtable'] ?? ''), 'type dbtable');
		$primary = $this->requireString((string)($type['primary'] ?? ''), 'type primary');

		$values = [
			'alias' => $alias,
			'dbtable' => $dbtable,
			'primary' => $primary
		];

		if (isset($type['id']) && $this->normalizeId($type['id']) !== null) {
			$values['id'] = $this->normalizeId($type['id']);
		}

		$insertId = $this->insertRow('base3system_systype', $values, true);
		if ($insertId) {
			return $insertId;
		}

		$existing = $this->getType($alias);
		return $existing['id'] ?? 0;
	}

	public function updateType(int|string $typeId, array $patch): void {
		$typeId = $this->requireId($typeId, 'type');
		$set = [];

		foreach (['alias', 'dbtable', 'primary'] as $field) {
			if (array_key_exists($field, $patch)) {
				$set[$field] = (string)$patch[$field];
			}
		}

		$this->updateRows('base3system_systype', $set, $this->eq('base3system_systype', 'id', $typeId), 1);
	}

	public function getModules(): array {
		return $this->fetchRows(
			'base3system_sysmodule',
			['module', 'type_id', 'single', 'description', 'created', 'changed'],
			null,
			[['element' => $this->fld('base3system_sysmodule', 'module'), 'direction' => 'ASC']]
		);
	}

	public function getModule(string $module): ?array {
		$module = trim($module);
		if ($module === '') return null;

		return $this->fetchRow(
			'base3system_sysmodule',
			['module', 'type_id', 'single', 'description', 'created', 'changed'],
			$this->eq('base3system_sysmodule', 'module', $module)
		);
	}

	public function createModule(array $module): string {
		$name = $this->requireString((string)($module['module'] ?? ''), 'module');
		$typeId = $this->requireId($module['type_id'] ?? null, 'type');

		$this->insertRow('base3system_sysmodule', [
			'module' => $name,
			'type_id' => $typeId,
			'single' => !empty($module['single']) ? 1 : 0,
			'description' => (string)($module['description'] ?? ''),
			'created' => $this->now(),
			'changed' => $this->now()
		], true);

		return $name;
	}

	public function updateModule(string $module, array $patch): void {
		$module = $this->requireString($module, 'module');
		$set = [];

		if (array_key_exists('type_id', $patch)) {
			$set['type_id'] = $this->requireId($patch['type_id'], 'type');
		}
		if (array_key_exists('single', $patch)) {
			$set['single'] = !empty($patch['single']) ? 1 : 0;
		}
		if (array_key_exists('description', $patch)) {
			$set['description'] = (string)$patch['description'];
		}
		if (!empty($set)) {
			$set['changed'] = $this->now();
		}

		$this->updateRows('base3system_sysmodule', $set, $this->eq('base3system_sysmodule', 'module', $module), 1);
	}

	public function getScopes(): array {
		return $this->fetchRows(
			'base3system_sysscope',
			['scope', 'description', 'created', 'changed'],
			null,
			[['element' => $this->fld('base3system_sysscope', 'scope'), 'direction' => 'ASC']]
		);
	}

	public function getScope(string $scope): ?array {
		$scope = trim($scope);
		if ($scope === '') return null;

		return $this->fetchRow(
			'base3system_sysscope',
			['scope', 'description', 'created', 'changed'],
			$this->eq('base3system_sysscope', 'scope', $scope)
		);
	}

	public function createScope(array $scope): string {
		$name = $this->requireString((string)($scope['scope'] ?? ''), 'scope');

		$this->insertRow('base3system_sysscope', [
			'scope' => $name,
			'description' => (string)($scope['description'] ?? ''),
			'created' => $this->now(),
			'changed' => $this->now()
		], true);

		return $name;
	}

	public function assignModuleToScope(string $module, string $scope): void {
		$this->insertRow('base3system_sysscopemodule', [
			'module' => $this->requireString($module, 'module'),
			'scope' => $this->requireString($scope, 'scope')
		], true);
	}

	public function removeModuleFromScope(string $module, string $scope): void {
		$this->deleteRows('base3system_sysscopemodule', $this->and([
			$this->eq('base3system_sysscopemodule', 'module', $this->requireString($module, 'module')),
			$this->eq('base3system_sysscopemodule', 'scope', $this->requireString($scope, 'scope'))
		]));
	}

	private function requireId(mixed $id, string $name): int {
		$id = $this->normalizeId($id);
		if ($id === null) {
			throw new \InvalidArgumentException('Invalid ' . $name . ' id.');
		}

		return $id;
	}

	private function requireString(string $value, string $name): string {
		$value = trim($value);
		if ($value === '') {
			throw new \InvalidArgumentException('Missing ' . $name . '.');
		}

		return $value;
	}
}
