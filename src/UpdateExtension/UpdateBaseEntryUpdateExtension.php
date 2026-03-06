<?php declare(strict_types=1);

namespace Memora\UpdateExtension;

use Base3\Api\ISortable;
use Memora\Api\IMemoraUpdateExtension;

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
