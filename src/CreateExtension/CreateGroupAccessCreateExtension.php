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

namespace Base3\Memora\CreateExtension;

use Base3\CoreFoundation\Api\ISortable;
use Base3\Memora\Api\IMemoraCreateExtension;

class CreateGroupAccessCreateExtension implements IMemoraCreateExtension, ISortable {

	public function isApplicable(array $entry): bool {
		return !empty($entry['groupaccess']) && is_array($entry['groupaccess']);
	}

	public function beforeCreate(array &$entry, array &$context): void {
		if (!isset($entry['groupaccess']) || !is_array($entry['groupaccess'])) return;

		$norm = [];

		foreach ($entry['groupaccess'] as $ga) {
			if (!is_array($ga)) continue;

			$groupId = $ga['group_id'] ?? null;
			$mode = $ga['mode'] ?? null;

			if (is_string($groupId) && ctype_digit($groupId)) $groupId = (int)$groupId;
			if (!is_int($groupId) || $groupId <= 0) continue;

			if (!is_string($mode)) continue;
			$mode = trim($mode);
			if ($mode === '') continue;

			$key = $groupId . ':' . $mode;
			$norm[$key] = [
				'group_id' => $groupId,
				'mode' => $mode
			];
		}

		$entry['groupaccess'] = array_values($norm);
	}

	public function create(array $entry, array &$context): void {
		if (empty($context['entry_id'])) {
			throw new \RuntimeException("Missing context['entry_id']. Ensure CreateBaseEntryCreateExtension ran first.");
		}

		$list = $entry['groupaccess'] ?? [];
		if (!is_array($list) || empty($list)) return;

		$values = [];
		foreach ($list as $ga) {
			if (!is_array($ga)) continue;
			$groupId = $ga['group_id'] ?? null;
			$mode = $ga['mode'] ?? null;

			if (!is_int($groupId) || $groupId <= 0) continue;
			if (!is_string($mode) || $mode === '') continue;

			$values[] = [
				'entry_id' => (int)$context['entry_id'],
				'group_id' => (int)$groupId,
				'mode' => $mode
			];
		}

		if (empty($values)) return;

		$context['transaction_queries'][] = [
			'type' => 'insert',
			'table' => 'base3system_sysgroupaccess',
			'values' => $values
		];
	}

	public function afterCreate(array $entry, array &$context): void {}

	public function getPriority(): int {
		return 650;
	}
}
