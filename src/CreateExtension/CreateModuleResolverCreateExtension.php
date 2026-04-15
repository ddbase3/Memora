<?php declare(strict_types=1);

namespace Memora\CreateExtension;

use Base3\Api\ISortable;
use Memora\Api\IMemoraCreateExtension;
use Memora\Api\IMemoraQueryService;

class CreateModuleResolverCreateExtension implements IMemoraCreateExtension, ISortable {

	public function __construct(
		private readonly IMemoraQueryService $dataqueryservice
	) {}

	public function isApplicable(array $entry): bool {
		return !empty($entry['module']);
	}

	public function beforeCreate(array &$entry, array &$context): void {
		$module = $this->normalizeModule($entry['module']);
		$definition = $this->loadModuleDefinition($module);

		if ($definition === null) {
			throw new \InvalidArgumentException("Unknown module '" . $module . "'.");
		}
		if (empty($definition['type_alias'])) {
			throw new \RuntimeException("Module '" . $module . "' is not mapped to a valid type alias.");
		}
		if (empty($definition['type_id'])) {
			throw new \RuntimeException("Module '" . $module . "' is not mapped to a valid type id.");
		}
		if (empty($definition['type_dbtable']) || empty($definition['type_primary'])) {
			throw new \RuntimeException("Module '" . $module . "' resolved an incomplete type definition.");
		}

		$entry['module'] = $module;

		if (isset($entry['type']) && is_string($entry['type']) && trim($entry['type']) !== '') {
			$currentType = trim($entry['type']);
			if ($currentType !== $definition['type_alias']) {
				throw new \InvalidArgumentException(
					"Conflicting create payload: module '" . $module . "' resolves to type '" . $definition['type_alias'] . "', but entry['type'] is '" . $currentType . "'."
				);
			}
		} else {
			$entry['type'] = $definition['type_alias'];
		}

		$entry['tags'] = $this->mergeTags(
			$entry['tags'] ?? [],
			$definition['tags']
		);

		$context['type_id'] = (int)$definition['type_id'];
		$context['type_alias'] = (string)$definition['type_alias'];
		$context['type_dbtable'] = (string)$definition['type_dbtable'];
		$context['type_primary'] = (string)$definition['type_primary'];
	}

	public function create(array $entry, array &$context): void {}

	public function afterCreate(array $entry, array &$context): void {}

	public function getPriority(): int {
		return 90;
	}

	protected function normalizeModule(mixed $module): string {
		if (is_array($module)) {
			$values = [];
			foreach ($module as $item) {
				$item = trim((string)$item);
				if ($item === '') continue;
				$values[$item] = true;
			}

			$modules = array_keys($values);
			if (count($modules) !== 1) {
				throw new \InvalidArgumentException("createEntry with 'module' requires exactly one module.");
			}

			return $modules[0];
		}

		$module = trim((string)$module);
		if ($module === '') {
			throw new \InvalidArgumentException("createEntry requires a non-empty 'module'.");
		}

		return $module;
	}

	protected function loadModuleDefinition(string $module): ?array {
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
						'table' => 'base3system_systype',
						'field' => 'alias',
						'variant' => 'required'
					],
					'alias' => 'type_alias'
				],
				[
					'element' => [
						'type' => 'fld',
						'table' => 'base3system_systype',
						'field' => 'dbtable',
						'variant' => 'required'
					],
					'alias' => 'type_dbtable'
				],
				[
					'element' => [
						'type' => 'fld',
						'table' => 'base3system_systype',
						'field' => 'primary',
						'variant' => 'required'
					],
					'alias' => 'type_primary'
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
				'operator' => '=',
				'params' => [
					[
						'type' => 'fld',
						'table' => 'base3system_sysmodule',
						'field' => 'module'
					],
					$module
				]
			]
		]);

		$rows = $result->rows ?? [];
		if (empty($rows)) {
			return null;
		}

		$definition = [
			'module' => $module,
			'type_id' => null,
			'type_alias' => null,
			'type_dbtable' => null,
			'type_primary' => null,
			'tags' => []
		];

		foreach ($rows as $row) {
			if (
				array_key_exists('type_id', $row)
				&& $row['type_id'] !== null
				&& $row['type_id'] !== ''
			) {
				$definition['type_id'] = (int)$row['type_id'];
			}

			if (!empty($row['type_alias'])) {
				$definition['type_alias'] = (string)$row['type_alias'];
			}
			if (!empty($row['type_dbtable'])) {
				$definition['type_dbtable'] = (string)$row['type_dbtable'];
			}
			if (!empty($row['type_primary'])) {
				$definition['type_primary'] = (string)$row['type_primary'];
			}
			if (!empty($row['tag'])) {
				$definition['tags'][(string)$row['tag']] = true;
			}
		}

		$definition['tags'] = array_keys($definition['tags']);

		return $definition;
	}

	protected function mergeTags(mixed $currentTags, array $moduleTags): array {
		$tags = [];

		foreach ($this->normalizeTags($currentTags) as $tag) {
			$tags[$tag] = true;
		}
		foreach ($this->normalizeTags($moduleTags) as $tag) {
			$tags[$tag] = true;
		}

		return array_keys($tags);
	}

	protected function normalizeTags(mixed $tags): array {
		if (!is_array($tags)) {
			$tags = [$tags];
		}

		$result = [];
		foreach ($tags as $tag) {
			if (!is_string($tag)) continue;
			$tag = trim($tag);
			if ($tag === '') continue;
			$result[$tag] = true;
		}

		return array_keys($result);
	}
}
