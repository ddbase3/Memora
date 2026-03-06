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

			// Undirected relationship: store only one row (entry_id_1=new, entry_id_2=peer). View mirrors automatically.
			$values[] = [
				'entry_id_1' => (int)$context['entry_id'],
				'entry_id_2' => (int)$peerId
			];
		}

		if (empty($values)) return;

		$context['transaction_queries'][] = [
			'type' => 'insert',
			'table' => 'base3system_sysalloc',
			'values' => $values
		];
	}

	public function afterCreate(array $entry, array &$context): void {}

	public function getPriority(): int {
		return 500;
	}
}
