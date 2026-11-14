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
