<?php declare(strict_types=1);

namespace Memora\CreateExtension;

use Base3\Api\ISortable;
use Memora\Api\IMemoraCreateExtension;
use Memora\Api\IMemoraQueryService;

class CreateTypeResolverCreateExtension implements IMemoraCreateExtension, ISortable {

	public function __construct(private readonly IMemoraQueryService $dataqueryservice) {}

	public function isApplicable(array $entry): bool {
		return !empty($entry['type']) && is_string($entry['type']);
	}

	public function beforeCreate(array &$entry, array &$context): void {
		$typeAlias = trim((string)$entry['type']);
		if ($typeAlias === '') {
			throw new \InvalidArgumentException("createEntry requires entry['type'] as non-empty string.");
		}

		$query = [
			'type' => 'select',
			'table' => 'base3system_systype',
			'fields' => [
				[ 'element' => [ 'type' => 'fld', 'table' => 'base3system_systype', 'field' => 'id' ], 'alias' => 'id' ],
				[ 'element' => [ 'type' => 'fld', 'table' => 'base3system_systype', 'field' => 'alias' ], 'alias' => 'alias' ],
				[ 'element' => [ 'type' => 'fld', 'table' => 'base3system_systype', 'field' => 'dbtable' ], 'alias' => 'dbtable' ],
				[ 'element' => [ 'type' => 'fld', 'table' => 'base3system_systype', 'field' => 'primary' ], 'alias' => 'primary' ],
			],
			'where' => [
				'type' => 'op',
				'operator' => '=',
				'params' => [
					[ 'type' => 'fld', 'table' => 'base3system_systype', 'field' => 'alias' ],
					$typeAlias
				]
			],
			'limit' => 1
		];

		$result = $this->dataqueryservice->executeQuery($query);
		$row = $result->rows[0] ?? null;

		if (!$row) {
			throw new \RuntimeException("Unknown type alias: " . $typeAlias);
		}

		$context['type_id'] = (int)$row['id'];
		$context['type_alias'] = (string)$row['alias'];
		$context['type_dbtable'] = (string)$row['dbtable'];
		$context['type_primary'] = (string)$row['primary'];
	}

	public function create(array $entry, array &$context): void {
		// Type resolution happens in beforeCreate().
	}

	public function afterCreate(array $entry, array &$context): void {}

	public function getPriority(): int {
		return 100;
	}
}
