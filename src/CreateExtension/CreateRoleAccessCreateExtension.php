<?php declare(strict_types=1);

namespace Memora\CreateExtension;

use Base3\Api\ISortable;
use Memora\Api\IMemoraCreateExtension;

/**
 * Legacy no-op kept so older class maps do not write to the removed
 * base3system_sysroleaccess table.
 */
class CreateRoleAccessCreateExtension implements IMemoraCreateExtension, ISortable {

	public function isApplicable(array $entry): bool {
		return false;
	}

	public function beforeCreate(array &$entry, array &$context): void {
		unset($entry['roleaccess']);
	}

	public function create(array $entry, array &$context): void {}

	public function afterCreate(array $entry, array &$context): void {}

	public function getPriority(): int {
		return 675;
	}
}
