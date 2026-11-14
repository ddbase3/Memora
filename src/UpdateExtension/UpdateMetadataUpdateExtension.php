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

namespace Base3\Memora\UpdateExtension;

use Base3\CoreFoundation\Api\ISortable;
use Base3\Memora\Api\IMemoraUpdateExtension;
use Base3\Memora\Api\IMemoraQueryService;

class UpdateMetadataUpdateExtension implements IMemoraUpdateExtension, ISortable {

	public function __construct(private readonly IMemoraQueryService $dataqueryservice) {}

	public function isApplicable(array $patch): bool {
		return !empty($patch['setmetadata'])
			|| !empty($patch['unsetmetadata']);
	}

	public function beforeUpdate(array &$patch, array &$context): void {
		if (array_key_exists('setmetadata', $patch) && !is_array($patch['setmetadata'])) {
			throw new \InvalidArgumentException("updateEntry patch['setmetadata'] must be an array.");
		}
		if (array_key_exists('unsetmetadata', $patch) && !is_array($patch['unsetmetadata'])) {
			throw new \InvalidArgumentException("updateEntry patch['unsetmetadata'] must be an array.");
		}

		$set = [];

		foreach (($patch['setmetadata'] ?? []) as $name => $value) {
			if (!is_string($name)) continue;

			$name = trim($name);
			if ($name === '') continue;
			if ($value === null) continue;

			$set[$name] = $this->encodeMetadataValue($value);
		}

		$unset = [];

		foreach (($patch['unsetmetadata'] ?? []) as $name) {
			if (!is_string($name)) continue;

			$name = trim($name);
			if ($name === '') continue;

			$unset[$name] = true;
		}

		foreach (array_keys($unset) as $name) {
			unset($set[$name]);
		}

		$patch['setmetadata'] = $set;
		$patch['unsetmetadata'] = array_keys($unset);
	}

	public function update(array $patch, array &$context): void {
		$entryId = (int)($context['entry_id'] ?? 0);
		if ($entryId <= 0) {
			throw new \RuntimeException("Missing context['entry_id'] in update pipeline.");
		}

		$unset = $patch['unsetmetadata'] ?? [];
		if (!empty($unset)) {
			$context['transaction_queries'][] = [
				'type' => 'delete',
				'table' => 'base3system_sysmetadata',
				'where' => [
					'type' => 'op',
					'operator' => 'AND',
					'params' => [
						[
							'type' => 'op',
							'operator' => '=',
							'params' => [
								[ 'type' => 'fld', 'table' => 'base3system_sysmetadata', 'field' => 'entry_id' ],
								$entryId
							]
						],
						[
							'type' => 'op',
							'operator' => 'IN',
							'params' => [
								[ 'type' => 'fld', 'table' => 'base3system_sysmetadata', 'field' => 'name' ],
								$unset
							]
						]
					]
				]
			];
		}

		$set = $patch['setmetadata'] ?? [];
		foreach ($set as $name => $encoded) {
			if (!is_string($name) || $name === '') continue;
			if (!is_string($encoded)) continue;

			if ($this->metadataRowExists($entryId, $name)) {
				$context['transaction_queries'][] = [
					'type' => 'update',
					'table' => 'base3system_sysmetadata',
					'set' => [
						'data' => $encoded
					],
					'where' => [
						'type' => 'op',
						'operator' => 'AND',
						'params' => [
							[
								'type' => 'op',
								'operator' => '=',
								'params' => [
									[ 'type' => 'fld', 'table' => 'base3system_sysmetadata', 'field' => 'entry_id' ],
									$entryId
								]
							],
							[
								'type' => 'op',
								'operator' => '=',
								'params' => [
									[ 'type' => 'fld', 'table' => 'base3system_sysmetadata', 'field' => 'name' ],
									$name
								]
							]
						]
					],
					'limit' => 1
				];
				continue;
			}

			$context['transaction_queries'][] = [
				'type' => 'insert',
				'table' => 'base3system_sysmetadata',
				'values' => [[
					'entry_id' => $entryId,
					'name' => $name,
					'data' => $encoded
				]]
			];
		}
	}

	public function afterUpdate(array $patch, array &$context): void {}

	private function metadataRowExists(int $entryId, string $name): bool {
		$query = [
			'type' => 'select',
			'table' => 'base3system_sysmetadata',
			'fields' => [
				[ 'element' => [ 'type' => 'fld', 'table' => 'base3system_sysmetadata', 'field' => 'entry_id' ], 'alias' => 'entry_id' ]
			],
			'where' => [
				'type' => 'op',
				'operator' => 'AND',
				'params' => [
					[
						'type' => 'op',
						'operator' => '=',
						'params' => [
							[ 'type' => 'fld', 'table' => 'base3system_sysmetadata', 'field' => 'entry_id' ],
							$entryId
						]
					],
					[
						'type' => 'op',
						'operator' => '=',
						'params' => [
							[ 'type' => 'fld', 'table' => 'base3system_sysmetadata', 'field' => 'name' ],
							$name
						]
					]
				]
			],
			'limit' => 1
		];

		$result = $this->dataqueryservice->executeQuery($query);
		return !empty($result->rows[0]);
	}

	private function encodeMetadataValue(mixed $value): string {
		if (is_string($value)) {
			return $value;
		}

		$json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($json === false) {
			throw new \RuntimeException("Failed to encode metadata value.");
		}

		return $json;
	}

	public function getPriority(): int {
		return 800;
	}
}
