<?php declare(strict_types=1);

namespace Memora\Service;

use Base3\Usermanager\Api\IUsermanager;
use Memora\Api\IMemoraProfileService;
use ResourceFoundation\Api\IQueryCompiler;
use ResourceFoundation\Api\IQueryService;

/**
 * Fast and cached implementation of the profile service for Memora.
 *
 * Uses DataHawk (QueryCompiler + QueryService) to access
 * the base3system_sysprofile table.
 *
 * request-lifetime caching included (no APCu required).
 */
class MemoraProfileService implements IMemoraProfileService {

	private ?array $cachedActiveProfile = null;
	private array $cachedProfiles = [];

	public function __construct(
		private readonly IUsermanager $usermanager,
		private readonly IQueryCompiler $compiler,
		private readonly IQueryService $queryService
	) {}

	/**
	 * Returns the active profile for the current user.
	 * Request-lifetime cached (dramatically faster).
	 */
	public function getActiveProfile(?int $userId = null): ?array {
		// Return cached profile immediately
		if ($this->cachedActiveProfile !== null) {
			return $this->cachedActiveProfile;
		}

		$user = $userId ?? ($this->usermanager->getUser()?->id ?? null);
		if (!$user) {
			return null;
		}

		// Build DataHawk query
		$query = [
			'type' => 'select',
			'table' => 'base3system_sysprofile',
			'fields' => [
				['element' => ['type' => 'fld', 'table' => 'base3system_sysprofile', 'field' => 'id'],        'alias' => 'id'],
				['element' => ['type' => 'fld', 'table' => 'base3system_sysprofile', 'field' => 'user_id'],   'alias' => 'user_id'],
				['element' => ['type' => 'fld', 'table' => 'base3system_sysprofile', 'field' => 'name'],      'alias' => 'name'],
				['element' => ['type' => 'fld', 'table' => 'base3system_sysprofile', 'field' => 'profile'],   'alias' => 'profile'],
				['element' => ['type' => 'fld', 'table' => 'base3system_sysprofile', 'field' => 'standard'],  'alias' => 'standard'],
				['element' => ['type' => 'fld', 'table' => 'base3system_sysprofile', 'field' => 'protected'], 'alias' => 'protected'],
				['element' => ['type' => 'fld', 'table' => 'base3system_sysprofile', 'field' => 'active'],    'alias' => 'active'],
				['element' => ['type' => 'fld', 'table' => 'base3system_sysprofile', 'field' => 'archive'],   'alias' => 'archive']
			],
			'where' => [[
				'type' => 'op',
				'operator' => 'AND',
				'params' => [
					[
						'type' => 'op',
						'operator' => '=',
						'params' => [
							['type' => 'fld', 'table' => 'base3system_sysprofile', 'field' => 'user_id'],
							$user
						]
					],
					[
						'type' => 'op',
						'operator' => 'OR',
						'params' => [
							['type' => 'op', 'operator' => '=', 'params' => [
								['type' => 'fld', 'table' => 'base3system_sysprofile', 'field' => 'active'], 1
							]],
							['type' => 'op', 'operator' => '=', 'params' => [
								['type' => 'fld', 'table' => 'base3system_sysprofile', 'field' => 'standard'], 1
							]]
						]
					]
				]
			]],
			'order' => [
				['element' => ['type' => 'fld', 'table' => 'base3system_sysprofile', 'field' => 'active'], 'direction' => 'DESC']
			],
			'limit' => 1
		];

		// Execute once per request
		$result = $this->queryService->executeQuery($query);
		$this->cachedActiveProfile = $result->rows[0] ?? null;

		return $this->cachedActiveProfile;
	}

	/**
	 * Returns all profiles of a user.
	 * Cached per request per user.
	 */
	public function getProfiles(?int $userId = null, bool $includeArchived = false): array {
		$user = $userId ?? ($this->usermanager->getUser()?->id ?? null);
		if (!$user) {
			return [];
		}

		$cacheKey = $user . '-' . ($includeArchived ? 'with_archived' : 'no_archived');
		if (isset($this->cachedProfiles[$cacheKey])) {
			return $this->cachedProfiles[$cacheKey];
		}

		$where = [[
			'type' => 'op',
			'operator' => '=',
			'params' => [
				['type' => 'fld', 'table' => 'base3system_sysprofile', 'field' => 'user_id'],
				$user
			]
		]];

		if (!$includeArchived) {
			$where[] = [
				'type' => 'op',
				'operator' => '=',
				'params' => [
					['type' => 'fld', 'table' => 'base3system_sysprofile', 'field' => 'archive'],
					0
				]
			];
		}

		$query = [
			'type' => 'select',
			'table' => 'base3system_sysprofile',
			'fields' => [
				['element' => ['type' => 'fld', 'table' => 'base3system_sysprofile', 'field' => 'id'],       'alias' => 'id'],
				['element' => ['type' => 'fld', 'table' => 'base3system_sysprofile', 'field' => 'name'],     'alias' => 'name'],
				['element' => ['type' => 'fld', 'table' => 'base3system_sysprofile', 'field' => 'profile'],  'alias' => 'profile'],
				['element' => ['type' => 'fld', 'table' => 'base3system_sysprofile', 'field' => 'active'],   'alias' => 'active'],
				['element' => ['type' => 'fld', 'table' => 'base3system_sysprofile', 'field' => 'archive'], 'alias' => 'archive']
			],
			'where' => [[
				'type' => 'op',
				'operator' => 'AND',
				'params' => $where
			]],
			'order' => [
				['element' => ['type' => 'fld', 'table' => 'base3system_sysprofile', 'field' => 'active'], 'direction' => 'DESC'],
				['element' => ['type' => 'fld', 'table' => 'base3system_sysprofile', 'field' => 'name'],   'direction' => 'ASC']
			]
		];

		$sql = $this->compiler->compile($query);
		$result = $this->queryService->executeQuery($sql);

		$this->cachedProfiles[$cacheKey] = $result;
		return $result;
	}

	/**
	 * Sets a profile as active and correctly invalidates all caches.
	 */
	public function setActiveProfile(int $userId, int $profileId): void {
		// Invalidate both caches
		$this->cachedActiveProfile = null;
		$this->cachedProfiles = [];

		// Deactivate all profiles for this user
		$deactivate = [
			'table' => 'base3system_sysprofile',
			'type' => 'update',
			'set' => [['field' => 'active', 'value' => 0]],
			'where' => [[
				'type' => 'op',
				'operator' => '=',
				'params' => [
					['type' => 'fld', 'table' => 'base3system_sysprofile', 'field' => 'user_id'],
					$userId
				]
			]]
		];

		$this->queryService->executeQuery($this->compiler->compile($deactivate));

		// Activate selected profile
		$activate = [
			'table' => 'base3system_sysprofile',
			'type' => 'update',
			'set' => [['field' => 'active', 'value' => 1]],
			'where' => [[
				'type' => 'op',
				'operator' => 'AND',
				'params' => [
					[
						'type' => 'op',
						'operator' => '=',
						'params' => [
							['type' => 'fld', 'table' => 'base3system_sysprofile', 'field' => 'id'],
							$profileId
						]
					],
					[
						'type' => 'op',
						'operator' => '=',
						'params' => [
							['type' => 'fld', 'table' => 'base3system_sysprofile', 'field' => 'user_id'],
							$userId
						]
					]
				]
			]]
		];

		$this->queryService->executeQuery($this->compiler->compile($activate));
	}
}
