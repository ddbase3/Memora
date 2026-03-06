<?php declare(strict_types=1);

namespace Memora\CreateExtension;

use Base3\Api\ISortable;
use Memora\Api\IMemoraCreateExtension;

class CreateAllocsCreateExtension implements IMemoraCreateExtension, ISortable {

	public function isApplicable(array $entry): bool {
		return !empty($entry['allocs']) && is_array($entry['allocs']);
	}

	public function beforeCreate(array &$entry, array &$context): void {
		if (!isset($entry['allocs']) || !is_array($entry['allocs'])) return;

		$allocs = [];
		foreach ($entry['allocs'] as $a) {
			if (is_string($a) && ctype_digit($a)) $a = (int)$a;
			if (!is_int($a) || $a <= 0) continue;
			$allocs[$a] = true;
		}

		$entry['allocs'] = array_keys($allocs);
	}

	public function create(array $entry, array &$context): void {
		if (empty($context['entry_id'])) {
			throw new \RuntimeException("Missing context['entry_id']. Ensure CreateBaseEntryCreateExtension ran first.");
		}

		$allocs = $entry['allocs'] ?? [];
		if (!is_array($allocs) || empty($allocs)) return;

		$values = [];
		foreach ($allocs as $peerId) {
			if (is_string($peerId) && ctype_digit($peerId)) $peerId = (int)$peerId;
			if (!is_int($peerId) || $peerId <= 0) continue;
			if ($peerId === (int)$context['entry_id']) continue;

			$pair = $this->makeCanonicalPair((int)$context['entry_id'], $peerId);

			$values[$pair['entry_id_1'] . ':' . $pair['entry_id_2']] = [
				'entry_id_1' => $pair['entry_id_1'],
				'entry_id_2' => $pair['entry_id_2']
			];
		}

		if (empty($values)) return;

		$context['transaction_queries'][] = [
			'type' => 'insert',
			'ignore' => true,
			'table' => 'base3system_sysalloc',
			'values' => array_values($values)
		];
	}

	public function afterCreate(array $entry, array &$context): void {}

	private function makeCanonicalPair(int $entryId, int $peerId): array {
		if ($entryId < $peerId) {
			return [
				'entry_id_1' => $entryId,
				'entry_id_2' => $peerId
			];
		}

		return [
			'entry_id_1' => $peerId,
			'entry_id_2' => $entryId
		];
	}

	public function getPriority(): int {
		return 500;
	}
}
