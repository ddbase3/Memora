<?php declare(strict_types=1);

namespace Memora\CreateExtension;

use Base3\Api\ISortable;
use Memora\Api\IMemoraCreateExtension;

class CreateUserAccessCreateExtension implements IMemoraCreateExtension, ISortable {

	public function isApplicable(array $entry): bool {
		return !empty($entry['useraccess']) && is_array($entry['useraccess']);
	}

	public function beforeCreate(array &$entry, array &$context): void {
		if (!isset($entry['useraccess']) || !is_array($entry['useraccess'])) return;

		$norm = [];

		foreach ($entry['useraccess'] as $ua) {
			if (!is_array($ua)) continue;

			$userId = $ua['user_id'] ?? null;
			$mode = $ua['mode'] ?? null;

			if (is_string($userId) && ctype_digit($userId)) $userId = (int)$userId;
			if (!is_int($userId) || $userId <= 0) continue;

			if (!is_string($mode)) continue;
			$mode = trim($mode);
			if ($mode === '') continue;

			$key = $userId . ':' . $mode;
			$norm[$key] = [
				'user_id' => $userId,
				'mode' => $mode
			];
		}

		$entry['useraccess'] = array_values($norm);
	}

	public function create(array $entry, array &$context): void {
		if (empty($context['entry_id'])) {
			throw new \RuntimeException("Missing context['entry_id']. Ensure CreateBaseEntryCreateExtension ran first.");
		}

		$list = $entry['useraccess'] ?? [];
		if (!is_array($list) || empty($list)) return;

		$values = [];
		foreach ($list as $ua) {
			if (!is_array($ua)) continue;
			$userId = $ua['user_id'] ?? null;
			$mode = $ua['mode'] ?? null;

			if (!is_int($userId) || $userId <= 0) continue;
			if (!is_string($mode) || $mode === '') continue;

			$values[] = [
				'entry_id' => (int)$context['entry_id'],
				'user_id' => (int)$userId,
				'mode' => $mode
			];
		}

		if (empty($values)) return;

		$context['transaction_queries'][] = [
			'type' => 'insert',
			'table' => 'base3system_sysuseraccess',
			'values' => $values
		];
	}

	public function afterCreate(array $entry, array &$context): void {}

	public function getPriority(): int {
		return 600;
	}
}
