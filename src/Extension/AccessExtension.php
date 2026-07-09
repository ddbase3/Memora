<?php declare(strict_types=1);

namespace Memora\Extension;

use Base3\Api\ISortable;
use Base3\Usermanager\Api\IUsermanager;
use Base3\Usermanager\Permission;
use Memora\Api\IMemoraQueryExtension;

class AccessExtension implements IMemoraQueryExtension, ISortable {

	/** Default public (unauthenticated) user ID */
	private const DEFAULT_USER_ID = 1;

	/** Default group ID for all authenticated members */
	private const DEFAULT_GROUP_ID = 1;

	public function __construct(private readonly IUsermanager $usermanager) {}

	public function isApplicable(array $options): bool {
		return !$this->hasEntryAdminPermission();
	}

	public function applyToQuery(array $query, array $options): array {
		$user = $this->usermanager->getUser();

		$conditions = [
			$this->buildPublicUserAccessCondition()
		];

		if ($user && !empty($user->id)) {
			$conditions[] = [ 'type' => 'op', 'operator' => '=', 'params' => [
				[ 'type' => 'fld', 'table' => 'base3system_sysuseraccess', 'field' => 'user_id', 'variant' => 'required' ],
				$user->id
			]];
		}

		$groupIds = $this->getCurrentGroupIds();
		if (!empty($groupIds)) {
			$conditions[] = [ 'type' => 'op', 'operator' => 'IN', 'params' => [
				[ 'type' => 'fld', 'table' => 'base3system_sysgroupaccess', 'field' => 'group_id', 'variant' => 'optional' ],
				$groupIds
			]];
		}

		$query['where'][] = [
			'type' => 'op',
			'operator' => 'OR',
			'params' => $conditions
		];

		return $query;
	}

	public function processResult(array $rows, array $options): array {
		return $rows;
	}

	private function buildPublicUserAccessCondition(): array {
		return [
			'type' => 'op',
			'operator' => 'AND',
			'params' => [
				[ 'type' => 'op', 'operator' => '=', 'params' => [
					[ 'type' => 'fld', 'table' => 'base3system_sysuseraccess', 'field' => 'user_id', 'variant' => 'required' ],
					self::DEFAULT_USER_ID
				]],
				[ 'type' => 'op', 'operator' => '!=', 'params' => [
					[ 'type' => 'fld', 'table' => 'base3system_sysuseraccess', 'field' => 'mode', 'variant' => 'required' ],
					'owner'
				]]
			]
		];
	}

	private function getCurrentGroupIds(): array {
		$groups = $this->usermanager->getGroups();
		$groupIds = array_map(fn($g) => (int)$g->id, $groups ?? []);

		if (!in_array(self::DEFAULT_GROUP_ID, $groupIds, true)) {
			$groupIds[] = self::DEFAULT_GROUP_ID;
		}

		return array_values(array_unique(array_filter(
			$groupIds,
			static fn(int $id): bool => $id > 0
		)));
	}

	private function hasEntryAdminPermission(): bool {
		return $this->usermanager->can(Permission::for('entry', 'admin'));
	}

	public function getPriority(): int {
		return 780;
	}
}
