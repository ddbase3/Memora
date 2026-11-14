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

namespace Base3\Memora\UpdateExtension;

use Base3\CoreFoundation\Api\ISortable;
use Base3\Memora\Api\IMemoraUpdateExtension;

class UpdateBaseEntryUpdateExtension implements IMemoraUpdateExtension, ISortable {

	private const ALLOWED_SET_FIELDS = [
		'archive',
		'dellock',
		'connections'
	];

	public function isApplicable(array $patch): bool {
		return !empty($patch['set']) && is_array($patch['set']);
	}

	public function beforeUpdate(array &$patch, array &$context): void {
		if (!isset($patch['set']) || !is_array($patch['set'])) {
			return;
		}

		$normalized = [];

		foreach ($patch['set'] as $field => $value) {
			if (!is_string($field) || !in_array($field, self::ALLOWED_SET_FIELDS, true)) {
				continue;
			}

			switch ($field) {
				case 'archive':
				case 'dellock':
					if (is_bool($value)) {
						$value = $value ? 1 : 0;
					}
					if (is_string($value) && ctype_digit($value)) {
						$value = (int)$value;
					}
					if (!is_int($value) || ($value !== 0 && $value !== 1)) {
						throw new \InvalidArgumentException("updateEntry patch['set']['" . $field . "'] must be 0 or 1.");
					}
					$normalized[$field] = $value;
					break;

				case 'connections':
					if (is_string($value) && ctype_digit($value)) {
						$value = (int)$value;
					}
					if (!is_int($value) || $value < 0) {
						throw new \InvalidArgumentException("updateEntry patch['set']['connections'] must be an integer >= 0.");
					}
					$normalized[$field] = $value;
					break;
			}
		}

		$patch['set'] = $normalized;
	}

	public function update(array $patch, array &$context): void {
		if (empty($context['entry_id'])) {
			throw new \RuntimeException("Missing context['entry_id'] in update pipeline.");
		}

		$set = $patch['set'] ?? [];
		if (!is_array($set) || empty($set)) {
			return;
		}

		$context['transaction_queries'][] = [
			'type' => 'update',
			'table' => 'base3system_sysentry',
			'set' => $set,
			'where' => [
				'type' => 'op',
				'operator' => '=',
				'params' => [
					[ 'type' => 'fld', 'table' => 'base3system_sysentry', 'field' => 'id' ],
					(int)$context['entry_id']
				]
			],
			'limit' => 1
		];
	}

	public function afterUpdate(array $patch, array &$context): void {}

	public function getPriority(): int {
		return 100;
	}
}
