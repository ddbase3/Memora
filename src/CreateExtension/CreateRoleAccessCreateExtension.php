<?php declare(strict_types=1);

namespace Memora\CreateExtension;

use Base3\Api\ISortable;
use Memora\Api\IMemoraCreateExtension;

class CreateRoleAccessCreateExtension implements IMemoraCreateExtension, ISortable {

	public function isApplicable(array $entry): bool {
		return !empty($entry['roleaccess']) && is_array($entry['roleaccess']);
	}

	public function beforeCreate(array &$entry, array &$context): void {
		if (!isset($entry['roleaccess']) || !is_array($entry['roleaccess'])) return;

		$normalized = [];

		foreach ($entry['roleaccess'] as $item) {
			if (!is_array($item)) continue;

			$roleId = $this->normalizeRoleId($item['role_id'] ?? null);
			if ($roleId === null) continue;

			$normalized[$roleId] = [
				'role_id' => $roleId
			];
		}

		$entry['roleaccess'] = array_values($normalized);
	}

	public function create(array $entry, array &$context): void {
		if (empty($context['entry_id'])) {
			throw new \RuntimeException("Missing context['entry_id']. Ensure CreateBaseEntryCreateExtension ran first.");
		}

		$list = $entry['roleaccess'] ?? [];
		if (!is_array($list) || empty($list)) return;

		$values = [];
		foreach ($list as $item) {
			if (!is_array($item)) continue;

			$roleId = $this->normalizeRoleId($item['role_id'] ?? null);
			if ($roleId === null) continue;

			$values[] = [
				'entry_id' => (int)$context['entry_id'],
				'role_id' => $roleId
			];
		}

		if (empty($values)) return;

		$context['transaction_queries'][] = [
			'type' => 'insert',
			'ignore' => true,
			'table' => 'base3system_sysroleaccess',
			'values' => $values
		];
	}

	public function afterCreate(array $entry, array &$context): void {}

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
		return 675;
	}
}
