<?php declare(strict_types=1);

namespace Memora\UpdateExtension;

use Base3\Api\ISortable;
use Memora\Api\IMemoraUpdateExtension;

class UpdateGroupAccessUpdateExtension implements IMemoraUpdateExtension, ISortable {

	private const ALLOWED_MODES = [
		'visitor',
		'moderator'
	];

	public function isApplicable(array $patch): bool {
		return !empty($patch['addgroupaccess'])
			|| !empty($patch['removegroupaccess'])
			|| array_key_exists('replacegroupaccess', $patch);
	}

	public function beforeUpdate(array &$patch, array &$context): void {
		$hasReplace = array_key_exists('replacegroupaccess', $patch);
		$hasAddRemove = !empty($patch['addgroupaccess']) || !empty($patch['removegroupaccess']);

		if ($hasReplace && $hasAddRemove) {
			throw new \InvalidArgumentException("updateEntry patch must not combine replacegroupaccess with addgroupaccess/removegroupaccess.");
		}

		if (array_key_exists('replacegroupaccess', $patch)) {
			$patch['replacegroupaccess'] = $this->normalizeAccessList($patch['replacegroupaccess'], 'replacegroupaccess');
		}
		if (array_key_exists('addgroupaccess', $patch)) {
			$patch['addgroupaccess'] = $this->normalizeAccessList($patch['addgroupaccess'], 'addgroupaccess');
		}
		if (array_key_exists('removegroupaccess', $patch)) {
			$patch['removegroupaccess'] = $this->normalizeAccessList($patch['removegroupaccess'], 'removegroupaccess');
		}
	}

	public function update(array $patch, array &$context): void {
		$entryId = (int)($context['entry_id'] ?? 0);
		if ($entryId <= 0) {
			throw new \RuntimeException("Missing context['entry_id'] in update pipeline.");
		}

		if (array_key_exists('replacegroupaccess', $patch)) {
			$context['transaction_queries'][] = [
				'type' => 'delete',
				'table' => 'base3system_sysgroupaccess',
				'where' => [
					'type' => 'op',
					'operator' => '=',
					'params' => [
						[ 'type' => 'fld', 'table' => 'base3system_sysgroupaccess', 'field' => 'entry_id' ],
						$entryId
					]
				]
			];

			$replace = $patch['replacegroupaccess'] ?? [];
			if (!empty($replace)) {
				$values = [];
				foreach ($replace as $item) {
					$values[] = [
						'entry_id' => $entryId,
						'group_id' => $item['group_id'],
						'mode' => $item['mode']
					];
				}

				$context['transaction_queries'][] = [
					'type' => 'insert',
					'ignore' => true,
					'table' => 'base3system_sysgroupaccess',
					'values' => $values
				];
			}

			return;
		}

		$remove = $patch['removegroupaccess'] ?? [];
		if (!empty($remove)) {
			$or = [];

			foreach ($remove as $item) {
				$or[] = [
					'type' => 'op',
					'operator' => 'AND',
					'params' => [
						[
							'type' => 'op',
							'operator' => '=',
							'params' => [
								[ 'type' => 'fld', 'table' => 'base3system_sysgroupaccess', 'field' => 'entry_id' ],
								$entryId
							]
						],
						[
							'type' => 'op',
							'operator' => '=',
							'params' => [
								[ 'type' => 'fld', 'table' => 'base3system_sysgroupaccess', 'field' => 'group_id' ],
								$item['group_id']
							]
						],
						[
							'type' => 'op',
							'operator' => '=',
							'params' => [
								[ 'type' => 'fld', 'table' => 'base3system_sysgroupaccess', 'field' => 'mode' ],
								$item['mode']
							]
						]
					]
				];
			}

			$context['transaction_queries'][] = [
				'type' => 'delete',
				'table' => 'base3system_sysgroupaccess',
				'where' => [
					'type' => 'op',
					'operator' => 'OR',
					'params' => $or
				]
			];
		}

		$add = $patch['addgroupaccess'] ?? [];
		if (!empty($add)) {
			$values = [];
			foreach ($add as $item) {
				$values[] = [
					'entry_id' => $entryId,
					'group_id' => $item['group_id'],
					'mode' => $item['mode']
				];
			}

			$context['transaction_queries'][] = [
				'type' => 'insert',
				'ignore' => true,
				'table' => 'base3system_sysgroupaccess',
				'values' => $values
			];
		}
	}

	public function afterUpdate(array $patch, array &$context): void {}

	private function normalizeAccessList(mixed $value, string $key): array {
		if (!is_array($value)) {
			throw new \InvalidArgumentException("updateEntry patch['" . $key . "'] must be an array.");
		}

		$normalized = [];

		foreach ($value as $item) {
			if (!is_array($item)) continue;

			$groupId = $item['group_id'] ?? null;
			$mode = $item['mode'] ?? null;

			if (is_string($groupId) && ctype_digit($groupId)) {
				$groupId = (int)$groupId;
			}
			if (!is_int($groupId) || $groupId <= 0) {
				continue;
			}

			if (!is_string($mode)) {
				continue;
			}
			$mode = trim($mode);
			if (!in_array($mode, self::ALLOWED_MODES, true)) {
				continue;
			}

			$normalized[$groupId . ':' . $mode] = [
				'group_id' => $groupId,
				'mode' => $mode
			];
		}

		return array_values($normalized);
	}

	public function getPriority(): int {
		return 700;
	}
}
