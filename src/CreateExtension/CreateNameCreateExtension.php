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

class CreateNameCreateExtension implements IMemoraCreateExtension, ISortable {

	private const DEFAULT_LANG_ID = 1;

	public function isApplicable(array $entry): bool {
		if (!empty($entry['name']) && is_string($entry['name'])) return true;
		if (!empty($entry['data']) && is_array($entry['data']) && !empty($entry['data']['name']) && is_string($entry['data']['name'])) return true;
		return false;
	}

	public function beforeCreate(array &$entry, array &$context): void {}

	public function create(array $entry, array &$context): void {
		if (empty($context['entry_id'])) {
			throw new \RuntimeException("Missing context['entry_id']. Ensure CreateBaseEntryCreateExtension ran first.");
		}

		$name = null;
		if (!empty($entry['name']) && is_string($entry['name'])) {
			$name = trim($entry['name']);
		} elseif (!empty($entry['data']) && is_array($entry['data']) && !empty($entry['data']['name']) && is_string($entry['data']['name'])) {
			$name = trim($entry['data']['name']);
		}

		if ($name === null || $name === '') return;

		$context['transaction_queries'][] = [
			'type' => 'insert',
			'table' => 'base3system_sysname',
			'values' => [[
				'entry_id' => (int)$context['entry_id'],
				'lang_id' => self::DEFAULT_LANG_ID,
				'name' => $name
			]]
		];
	}

	public function afterCreate(array $entry, array &$context): void {}

	public function getPriority(): int {
		return 300;
	}
}
