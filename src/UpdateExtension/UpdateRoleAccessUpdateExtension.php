<?php declare(strict_types=1);

namespace Memora\UpdateExtension;

use Base3\Api\ISortable;
use Memora\Api\IMemoraUpdateExtension;

/**
 * Legacy no-op kept so older class maps do not write to the removed
 * base3system_sysroleaccess table.
 */
class UpdateRoleAccessUpdateExtension implements IMemoraUpdateExtension, ISortable {

	public function isApplicable(array $patch): bool {
		return false;
	}

	public function beforeUpdate(array &$patch, array &$context): void {
		unset($patch['addroleaccess'], $patch['removeroleaccess'], $patch['replaceroleaccess']);
	}

	public function update(array $patch, array &$context): void {}

	public function afterUpdate(array $patch, array &$context): void {}

	public function getPriority(): int {
		return 750;
	}
}
