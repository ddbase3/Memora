<?php declare(strict_types=1);

namespace Memora\Extension;

use Base3\Api\ISortable;
use Memora\Api\IMemoraQueryExtension;
use Memora\Api\IMemoraQueryService;

class FilterByModuleExtension implements IMemoraQueryExtension, ISortable {

	public function __construct(
		private readonly IMemoraQueryService $dataqueryservice
	) {}

	public function isApplicable(array $options): bool {
		return !empty($options['module']);
	}

	public function applyToQuery(array $query, array $options): array {
		$modules = $this->normalizeModules($options['module'] ?? []);
		if (empty($modules)) {
			return $query;
		}

		$definitions = $this->loadModuleDefinitions($modules);

		$moduleClauses = [];
		foreach ($modules as $moduleIndex => $module) {
			$definition = $definitions[$module] ?? null;
			if ($definition === null) {
				continue;
			}

			$clause = $this->buildModuleClause($definition, $moduleIndex);
			if ($clause !== null) {
				$moduleClauses[] = $clause;
			}
		}

		if (empty($moduleClauses)) {
			$query['where'][] = $this->buildImpossibleClause();
			return $query;
		}

		$query['where'][] = count($moduleClauses) === 1
			? $moduleClauses[0]
			: [
				'type' => 'op',
				'operator' => 'OR',
				'params' => $moduleClauses
			];

		return $query;
	}

	public function processResult(array $rows, array $options): array {
		return $rows;
	}

	public function getPriority(): int {
		return 120;
	}

	protected function normalizeModules(mixed $modules): array {
		$modules = is_array($modules) ? $modules : [$modules];
		$modules = array_map(
			static fn(mixed $value): string => trim((string)$value),
			$modules
		);
		$modules = array_filter(
			$modules,
			static fn(string $value): bool => $value !== ''
		);

		return array_values(array_unique($modules));
	}

	protected function loadModuleDefinitions(array $modules): array {
		$definitions = [];
		foreach ($modules as $module) {
			$definitions[$module] = [
				'module' => $module,
				'type_id' => null,
				'tags' => []
			];
		}

		$result = $this->dataqueryservice->executeQuery([
			'type' => 'select',
			'table' => 'base3system_sysmodule',
			'fields' => [
				[
					'element' => [
						'type' => 'fld',
						'table' => 'base3system_sysmodule',
						'field' => 'module'
					],
					'alias' => 'module'
				],
				[
					'element' => [
						'type' => 'fld',
						'table' => 'base3system_sysmodule',
						'field' => 'type_id'
					],
					'alias' => 'type_id'
				],
				[
					'element' => [
						'type' => 'fld',
						'table' => 'base3system_sysmoduletag',
						'field' => 'tag',
						'variant' => 'optional'
					],
					'alias' => 'tag'
				]
			],
			'where' => [
				'type' => 'op',
				'operator' => count($modules) === 1 ? '=' : 'IN',
				'params' => [
					[
						'type' => 'fld',
						'table' => 'base3system_sysmodule',
						'field' => 'module'
					],
					count($modules) === 1 ? $modules[0] : $modules
				]
			]
		]);

		foreach (($result->rows ?? []) as $row) {
			$module = (string)($row['module'] ?? '');
			if ($module === '' || !isset($definitions[$module])) {
				continue;
			}

			if (
				array_key_exists('type_id', $row)
				&& $row['type_id'] !== null
				&& $row['type_id'] !== ''
			) {
				$definitions[$module]['type_id'] = (int)$row['type_id'];
			}

			if (!empty($row['tag'])) {
				$definitions[$module]['tags'][] = (string)$row['tag'];
			}
		}

		foreach ($definitions as $module => $definition) {
			$definitions[$module]['tags'] = array_values(array_unique($definition['tags']));
		}

		return $definitions;
	}

	protected function buildModuleClause(array $definition, int $moduleIndex): ?array {
		$params = [];

		if (
			array_key_exists('type_id', $definition)
			&& $definition['type_id'] !== null
			&& $definition['type_id'] !== ''
		) {
			$params[] = [
				'type' => 'op',
				'operator' => '=',
				'params' => [
					[
						'type' => 'fld',
						'table' => 'base3system_sysentry',
						'field' => 'type_id'
					],
					(int)$definition['type_id']
				]
			];
		}

		foreach (($definition['tags'] ?? []) as $tagIndex => $tag) {
			$params[] = [
				'type' => 'op',
				'operator' => '=',
				'params' => [
					[
						'type' => 'fld',
						'table' => 'base3system_systag',
						'tablealias' => 'modtag_' . $moduleIndex . '_' . $tagIndex,
						'field' => 'tag',
						'variant' => 'required'
					],
					$tag
				]
			];
		}

		if (empty($params)) {
			return null;
		}

		return count($params) === 1
			? $params[0]
			: [
				'type' => 'op',
				'operator' => 'AND',
				'params' => $params
			];
	}

	protected function buildImpossibleClause(): array {
		return [
			'type' => 'op',
			'operator' => '=',
			'params' => [
				[
					'type' => 'fld',
					'table' => 'base3system_sysentry',
					'field' => 'id'
				],
				-1
			]
		];
	}
}
