<?php declare(strict_types=1);

namespace Memora\UpdateExtension;

use Base3\Api\ISortable;
use Memora\Api\IMemoraUpdateExtension;
use Memora\Api\IMemoraQueryService;

class UpdateNameUpdateExtension implements IMemoraUpdateExtension, ISortable {

	private const DEFAULT_LANG_ID = 1;

	public function __construct(private readonly IMemoraQueryService $dataqueryservice) {}

	public function isApplicable(array $patch): bool {
		return array_key_exists('setname', $patch);
	}

	public function beforeUpdate(array &$patch, array &$context): void {
		$name = $patch['setname'] ?? null;

		if (!is_string($name)) {
			throw new \InvalidArgumentException("updateEntry patch['setname'] must be a string.");
		}

		$name = trim($name);
		if ($name === '') {
			throw new \InvalidArgumentException("updateEntry patch['setname'] must not be empty.");
		}

		$patch['setname'] = $name;
	}

	public function update(array $patch, array &$context): void {
		$entryId = (int)($context['entry_id'] ?? 0);
		if ($entryId <= 0) {
			throw new \RuntimeException("Missing context['entry_id'] in update pipeline.");
		}

		$name = $patch['setname'] ?? null;
		if (!is_string($name) || $name === '') {
			return;
		}

		if ($this->nameRowExists($entryId)) {
			$context['transaction_queries'][] = [
				'type' => 'update',
				'table' => 'base3system_sysname',
				'set' => [
					'name' => $name
				],
				'where' => [
					'type' => 'op',
					'operator' => 'AND',
					'params' => [
						[
							'type' => 'op',
							'operator' => '=',
							'params' => [
								[ 'type' => 'fld', 'table' => 'base3system_sysname', 'field' => 'entry_id' ],
								$entryId
							]
						],
						[
							'type' => 'op',
							'operator' => '=',
							'params' => [
								[ 'type' => 'fld', 'table' => 'base3system_sysname', 'field' => 'lang_id' ],
								self::DEFAULT_LANG_ID
							]
						]
					]
				],
				'limit' => 1
			];
			return;
		}

		$context['transaction_queries'][] = [
			'type' => 'insert',
			'table' => 'base3system_sysname',
			'values' => [[
				'entry_id' => $entryId,
				'lang_id' => self::DEFAULT_LANG_ID,
				'name' => $name
			]]
		];
	}

	public function afterUpdate(array $patch, array &$context): void {}

	private function nameRowExists(int $entryId): bool {
		$query = [
			'type' => 'select',
			'table' => 'base3system_sysname',
			'fields' => [
				[ 'element' => [ 'type' => 'fld', 'table' => 'base3system_sysname', 'field' => 'entry_id' ], 'alias' => 'entry_id' ]
			],
			'where' => [
				'type' => 'op',
				'operator' => 'AND',
				'params' => [
					[
						'type' => 'op',
						'operator' => '=',
						'params' => [
							[ 'type' => 'fld', 'table' => 'base3system_sysname', 'field' => 'entry_id' ],
							$entryId
						]
					],
					[
						'type' => 'op',
						'operator' => '=',
						'params' => [
							[ 'type' => 'fld', 'table' => 'base3system_sysname', 'field' => 'lang_id' ],
							self::DEFAULT_LANG_ID
						]
					]
				]
			],
			'limit' => 1
		];

		$result = $this->dataqueryservice->executeQuery($query);
		return !empty($result->rows[0]);
	}

	public function getPriority(): int {
		return 200;
	}
}
