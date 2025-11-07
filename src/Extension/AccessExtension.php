<?php declare(strict_types=1);

namespace Memora\Extension;

use Base3\Api\ISortable;
use Base3\Usermanager\Api\IUsermanager;
use Memora\Api\IEntryQueryExtension;

class AccessExtension implements IEntryQueryExtension, ISortable {

	/** Default public (unauthenticated) user ID */
	private const DEFAULT_USER_ID = 1;

	/** Default group ID for all authenticated members */
	private const DEFAULT_GROUP_ID = 1;

	public function __construct(private readonly IUsermanager $usermanager) {}

	public function isApplicable(array $options): bool {
		$user = $this->usermanager->getUser();
		return !$user || $user->role != 'admin';
	}

	public function applyToQuery(array $query, array $options): array {
		$user = $this->usermanager->getUser();
		$groups = $this->usermanager->getGroups();

		// No user context → only public entries
		if (!$user) {
			$query['where'][] = [
				'type' => 'op',
				'operator' => 'AND',
				'params' => [
					[ 'type' => 'op', 'operator' => '=', 'params' => [
						[ 'type' => 'fld', 'table' => 'base3system_useraccess', 'field' => 'user_id', 'variant' => 'required' ],
						self::DEFAULT_USER_ID
					]],
					[ 'type' => 'op', 'operator' => '!=', 'params' => [
						[ 'type' => 'fld', 'table' => 'base3system_useraccess', 'field' => 'mode', 'variant' => 'required' ],
						'owner'
					]]
				]
			];
			return $query;
		}

		// Build base conditions
		$userConds = [];
		$groupConds = [];

		// Direct user access
		$userConds[] = [ 'type' => 'op', 'operator' => '=', 'params' => [
			[ 'type' => 'fld', 'table' => 'base3system_useraccess', 'field' => 'user_id', 'variant' => 'required' ],
			$user->id
		]];

		// Public (default user) access, excluding owner-only
		$userConds[] = [
			'type' => 'op',
			'operator' => 'AND',
			'params' => [
				[ 'type' => 'op', 'operator' => '=', 'params' => [
					[ 'type' => 'fld', 'table' => 'base3system_useraccess', 'field' => 'user_id', 'variant' => 'required' ],
					self::DEFAULT_USER_ID
				]],
				[ 'type' => 'op', 'operator' => '!=', 'params' => [
					[ 'type' => 'fld', 'table' => 'base3system_useraccess', 'field' => 'mode', 'variant' => 'required' ],
					'owner'
				]]
			]
		];

		// Group access (specific + default group)
		$groupIds = array_map(fn($g) => (int) $g->id, $groups ?? []);
		// Ensure default group (1) is included for all logged-in users
		if (!in_array(self::DEFAULT_GROUP_ID, $groupIds, true)) {
			$groupIds[] = self::DEFAULT_GROUP_ID;
		}

		if (!empty($groupIds)) {
			$groupConds[] = [ 'type' => 'op', 'operator' => 'IN', 'params' => [
				[ 'type' => 'fld', 'table' => 'base3system_groupaccess', 'field' => 'group_id', 'variant' => 'optional' ],
				$groupIds
			]];
		}

		// Combine all with OR
		$combined = [ 'type' => 'op', 'operator' => 'OR', 'params' => [
			[ 'type' => 'op', 'operator' => 'OR', 'params' => $userConds ],
			[ 'type' => 'op', 'operator' => 'OR', 'params' => $groupConds ]
		]];

		$query['where'][] = $combined;
		return $query;
	}

	public function processResult(array $rows, array $options): array {
		return $rows;
	}

	public function getPriority(): int {
		return 780;
	}
}
