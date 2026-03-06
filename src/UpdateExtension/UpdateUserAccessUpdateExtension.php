<?php declare(strict_types=1);

namespace Memora\UpdateExtension;

use Base3\Api\ISortable;
use Memora\Api\IMemoraUpdateExtension;

class UpdateUserAccessUpdateExtension implements IMemoraUpdateExtension, ISortable {

	private const ALLOWED_MODES = [
		'visitor',
		'moderator',
		'owner'
	];

	public function isApplicable(array $patch): bool {
		return !empty($patch['adduseraccess'])
			|| !empty($patch['removeuseraccess'])
			|| array_key_exists('replaceuseraccess', $patch);
	}

	public function beforeUpdate(array &$patch, array &$context): void {
		$hasReplace = array_key_exists('replaceuseraccess', $patch);
		$hasAddRemove = !empty($patch['adduseraccess']) || !empty($patch['removeuseraccess']);

		if ($hasReplace && $hasAddRemove) {
			throw new \InvalidArgumentException("updateEntry patch must not combine replaceuseraccess with adduseraccess/removeuseraccess.");
		}

		if (array_key_exists('replaceuseraccess', $patch)) {
			$patch['replaceuseraccess'] = $this->normalizeAccessList($patch['replaceuseraccess'], 'replaceuseraccess');
		}
		if (array_key_exists('adduseraccess', $patch)) {
			$patch['adduseraccess'] = $this->normalizeAccessList($patch['adduseraccess'], 'adduseraccess');
		}
		if (array_key_exists('removeuseraccess', $patch)) {
			$patch['removeuseraccess'] = $this->normalizeAccessList($patch['removeuseraccess'], 'removeuseraccess');
		}
	}

	public function update(array $patch, array &$context): void {
		$entryId = (int)($context['entry_id'] ?? 0);
		if ($entryId <= 0) {
			throw new \RuntimeException("Missing context['entry_id'] in update pipeline.");
		}

		if (array_key_exists('replaceuseraccess', $patch)) {
			$context['transaction_queries'][] = [
				'type' => 'delete',
				'table' => 'base3system_sysuseraccess',
				'where' => [
					'type' => 'op',
					'operator' => '=',
					'params' => [
						[ 'type' => 'fld', 'table' => 'base3system_sysuseraccess', 'field' => 'entry_id' ],
						$entryId
					]
				]
			];

			$replace = $patch['replaceuseraccess'] ?? [];
			if (!empty($replace)) {
				$values = [];
				foreach ($replace as $item) {
					$values[] = [
						'entry_id' => $entryId,
						'user_id' => $item['user_id'],
						'mode' => $item['mode']
					];
				}

				$context['transaction_queries'][] = [
					'type' => 'insert',
					'ignore' => true,
					'table' => 'base3system_sysuseraccess',
					'values' => $values
				];
			}

			return;
		}

		$remove = $patch['removeuseraccess'] ?? [];
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
								[ 'type' => 'fld', 'table' => 'base3system_sysuseraccess', 'field' => 'entry_id' ],
								$entryId
							]
						],
						[
							'type' => 'op',
							'operator' => '=',
							'params' => [
								[ 'type' => 'fld', 'table' => 'base3system_sysuseraccess', 'field' => 'user_id' ],
								$item['user_id']
							]
						],
						[
							'type' => 'op',
							'operator' => '=',
							'params' => [
								[ 'type' => 'fld', 'table' => 'base3system_sysuseraccess', 'field' => 'mode' ],
								$item['mode']
							]
						]
					]
				];
			}

			$context['transaction_queries'][] = [
				'type' => 'delete',
				'table' => 'base3system_sysuseraccess',
				'where' => [
					'type' => 'op',
					'operator' => 'OR',
					'params' => $or
				]
			];
		}

		$add = $patch['adduseraccess'] ?? [];
		if (!empty($add)) {
			$values = [];
			foreach ($add as $item) {
				$values[] = [
					'entry_id' => $entryId,
					'user_id' => $item['user_id'],
					'mode' => $item['mode']
				];
			}

			$context['transaction_queries'][] = [
				'type' => 'insert',
				'ignore' => true,
				'table' => 'base3system_sysuseraccess',
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

			$userId = $item['user_id'] ?? null;
			$mode = $item['mode'] ?? null;

			if (is_string($userId) && ctype_digit($userId)) {
				$userId = (int)$userId;
			}
			if (!is_int($userId) || $userId <= 0) {
				continue;
			}

			if (!is_string($mode)) {
				continue;
			}
			$mode = trim($mode);
			if (!in_array($mode, self::ALLOWED_MODES, true)) {
				continue;
			}

			$normalized[$userId . ':' . $mode] = [
				'user_id' => $userId,
				'mode' => $mode
			];
		}

		return array_values($normalized);
	}

	public function getPriority(): int {
		return 600;
	}
}
