<?php declare(strict_types=1);

namespace Memora\UpdateExtension;

use Base3\Api\ISortable;
use Memora\Api\IMemoraUpdateExtension;

class UpdateRoleAccessUpdateExtension implements IMemoraUpdateExtension, ISortable {

	public function isApplicable(array $patch): bool {
		return !empty($patch['addroleaccess'])
			|| !empty($patch['removeroleaccess'])
			|| array_key_exists('replaceroleaccess', $patch);
	}

	public function beforeUpdate(array &$patch, array &$context): void {
		$hasReplace = array_key_exists('replaceroleaccess', $patch);
		$hasAddRemove = !empty($patch['addroleaccess']) || !empty($patch['removeroleaccess']);

		if ($hasReplace && $hasAddRemove) {
			throw new \InvalidArgumentException("updateEntry patch must not combine replaceroleaccess with addroleaccess/removeroleaccess.");
		}

		if (array_key_exists('replaceroleaccess', $patch)) {
			$patch['replaceroleaccess'] = $this->normalizeAccessList($patch['replaceroleaccess'], 'replaceroleaccess');
		}
		if (array_key_exists('addroleaccess', $patch)) {
			$patch['addroleaccess'] = $this->normalizeAccessList($patch['addroleaccess'], 'addroleaccess');
		}
		if (array_key_exists('removeroleaccess', $patch)) {
			$patch['removeroleaccess'] = $this->normalizeAccessList($patch['removeroleaccess'], 'removeroleaccess');
		}
	}

	public function update(array $patch, array &$context): void {
		$entryId = (int)($context['entry_id'] ?? 0);
		if ($entryId <= 0) {
			throw new \RuntimeException("Missing context['entry_id'] in update pipeline.");
		}

		if (array_key_exists('replaceroleaccess', $patch)) {
			$context['transaction_queries'][] = [
				'type' => 'delete',
				'table' => 'base3system_sysroleaccess',
				'where' => [
					'type' => 'op',
					'operator' => '=',
					'params' => [
						[ 'type' => 'fld', 'table' => 'base3system_sysroleaccess', 'field' => 'entry_id' ],
						$entryId
					]
				]
			];

			$replace = $patch['replaceroleaccess'] ?? [];
			if (!empty($replace)) {
				$values = [];
				foreach ($replace as $item) {
					$values[] = [
						'entry_id' => $entryId,
						'role_id' => $item['role_id']
					];
				}

				$context['transaction_queries'][] = [
					'type' => 'insert',
					'ignore' => true,
					'table' => 'base3system_sysroleaccess',
					'values' => $values
				];
			}

			return;
		}

		$remove = $patch['removeroleaccess'] ?? [];
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
								[ 'type' => 'fld', 'table' => 'base3system_sysroleaccess', 'field' => 'entry_id' ],
								$entryId
							]
						],
						[
							'type' => 'op',
							'operator' => '=',
							'params' => [
								[ 'type' => 'fld', 'table' => 'base3system_sysroleaccess', 'field' => 'role_id' ],
								$item['role_id']
							]
						]
					]
				];
			}

			$context['transaction_queries'][] = [
				'type' => 'delete',
				'table' => 'base3system_sysroleaccess',
				'where' => [
					'type' => 'op',
					'operator' => 'OR',
					'params' => $or
				]
			];
		}

		$add = $patch['addroleaccess'] ?? [];
		if (!empty($add)) {
			$values = [];
			foreach ($add as $item) {
				$values[] = [
					'entry_id' => $entryId,
					'role_id' => $item['role_id']
				];
			}

			$context['transaction_queries'][] = [
				'type' => 'insert',
				'ignore' => true,
				'table' => 'base3system_sysroleaccess',
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

			$roleId = $this->normalizeRoleId($item['role_id'] ?? null);
			if ($roleId === null) continue;

			$normalized[$roleId] = [
				'role_id' => $roleId
			];
		}

		return array_values($normalized);
	}

	private function normalizeRoleId(mixed $roleId): ?int {
		if (is_string($roleId) && ctype_digit($roleId)) {
			$roleId = (int)$roleId;
		}

		if (!is_int($roleId) || $roleId <= 0) {
			return null;
		}

		return $roleId;
	}

	public function getPriority(): int {
		return 750;
	}
}
