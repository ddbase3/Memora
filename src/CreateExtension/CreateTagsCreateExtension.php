<?php declare(strict_types=1);

namespace Memora\CreateExtension;

use Base3\Api\ISortable;
use Memora\Api\IMemoraCreateExtension;

class CreateTagsCreateExtension implements IMemoraCreateExtension, ISortable {

	public function isApplicable(array $entry): bool {
		return !empty($entry['tags']) && is_array($entry['tags']);
	}

	public function beforeCreate(array &$entry, array &$context): void {
		if (!isset($entry['tags']) || !is_array($entry['tags'])) return;

		$tags = [];
		foreach ($entry['tags'] as $t) {
			if (!is_string($t)) continue;
			$t = trim($t);
			if ($t === '') continue;
			$tags[$t] = true;
		}

		$entry['tags'] = array_keys($tags);
	}

	public function create(array $entry, array &$context): void {
		if (empty($context['entry_id'])) {
			throw new \RuntimeException("Missing context['entry_id']. Ensure CreateBaseEntryCreateExtension ran first.");
		}

		$tags = $entry['tags'] ?? [];
		if (!is_array($tags) || empty($tags)) return;

		$values = [];
		foreach ($tags as $tag) {
			if (!is_string($tag) || $tag === '') continue;
			$values[] = [
				'entry_id' => (int)$context['entry_id'],
				'tag' => $tag
			];
		}

		if (empty($values)) return;

		$context['transaction_queries'][] = [
			'type' => 'insert',
			'table' => 'base3system_systag',
			'values' => $values
		];
	}

	public function afterCreate(array $entry, array &$context): void {}

	public function getPriority(): int {
		return 400;
	}
}
