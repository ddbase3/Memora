<?php declare(strict_types=1);

namespace Memora\UpdateExtension;

use Base3\Api\ISortable;
use Memora\Api\IMemoraUpdateExtension;

class UpdateTouchUpdateExtension implements IMemoraUpdateExtension, ISortable {

	private const SUPPORTED_KEYS = [
		'set',
		'setname',
		'setdata',
		'unsetdata',
		'setmetadata',
		'unsetmetadata',
		'addtags',
		'removetags',
		'replacetags',
		'addallocs',
		'removeallocs',
		'replaceallocs',
		'adduseraccess',
		'removeuseraccess',
		'replaceuseraccess',
		'addgroupaccess',
		'removegroupaccess',
		'replacegroupaccess'
	];

	public function isApplicable(array $patch): bool {
		foreach (self::SUPPORTED_KEYS as $key) {
			if (array_key_exists($key, $patch)) {
				return true;
			}
		}

		return false;
	}

	public function beforeUpdate(array &$patch, array &$context): void {}

	public function update(array $patch, array &$context): void {
		$entryId = (int)($context['entry_id'] ?? 0);
		if ($entryId <= 0) {
			throw new \RuntimeException("Missing context['entry_id'] in update pipeline.");
		}

		$context['transaction_queries'][] = [
			'type' => 'update',
			'table' => 'base3system_sysentry',
			'set' => [
				'changed' => date('Y-m-d H:i:s')
			],
			'where' => [
				'type' => 'op',
				'operator' => '=',
				'params' => [
					[ 'type' => 'fld', 'table' => 'base3system_sysentry', 'field' => 'id' ],
					$entryId
				]
			],
			'limit' => 1
		];
	}

	public function afterUpdate(array $patch, array &$context): void {}

	public function getPriority(): int {
		return 1000;
	}
}
