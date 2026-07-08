<?php declare(strict_types=1);

namespace Memora\Service;

use Base3\Usermanager\Api\IUsermanager;
use Memora\Api\IMemoraQueryService;
use ResourceFoundation\Api\IEntityProfileService;

/**
 * Memora implementation of the generic ResourceFoundation profile service.
 *
 * Profiles are stored in base3system_sysprofile and used to enrich entity query
 * options with user-specific filters.
 */
class MemoraProfileService extends AbstractMemoraTableService implements IEntityProfileService {

	private ?array $cachedActiveProfile = null;
	private array $cachedProfiles = [];

	public function __construct(
		IMemoraQueryService $dataqueryservice,
		private readonly IUsermanager $usermanager
	) {
		parent::__construct($dataqueryservice);
	}

	public function getActiveProfile(?int $userId = null): ?array {
		$userId = $this->resolveUserId($userId);
		if ($userId === null) {
			return null;
		}

		if ($this->cachedActiveProfile !== null && (int)($this->cachedActiveProfile['user_id'] ?? 0) === $userId) {
			return $this->cachedActiveProfile;
		}

		$where = $this->and([
			$this->eq('base3system_sysprofile', 'user_id', $userId),
			$this->or([
				$this->eq('base3system_sysprofile', 'active', 1),
				$this->eq('base3system_sysprofile', 'standard', 1)
			])
		]);

		$this->cachedActiveProfile = $this->fetchRow(
			'base3system_sysprofile',
			$this->profileFields(),
			$where,
			[
				['element' => $this->fld('base3system_sysprofile', 'active'), 'direction' => 'DESC'],
				['element' => $this->fld('base3system_sysprofile', 'standard'), 'direction' => 'DESC'],
				['element' => $this->fld('base3system_sysprofile', 'name'), 'direction' => 'ASC']
			]
		);

		return $this->cachedActiveProfile;
	}

	public function getProfiles(?int $userId = null, bool $includeArchived = false): array {
		$userId = $this->resolveUserId($userId);
		if ($userId === null) {
			return [];
		}

		$cacheKey = $userId . ':' . ($includeArchived ? 'all' : 'active');
		if (isset($this->cachedProfiles[$cacheKey])) {
			return $this->cachedProfiles[$cacheKey];
		}

		$where = [$this->eq('base3system_sysprofile', 'user_id', $userId)];
		if (!$includeArchived) {
			$where[] = $this->eq('base3system_sysprofile', 'archive', 0);
		}

		$this->cachedProfiles[$cacheKey] = $this->fetchRows(
			'base3system_sysprofile',
			$this->profileFields(),
			$this->and($where),
			[
				['element' => $this->fld('base3system_sysprofile', 'active'), 'direction' => 'DESC'],
				['element' => $this->fld('base3system_sysprofile', 'standard'), 'direction' => 'DESC'],
				['element' => $this->fld('base3system_sysprofile', 'name'), 'direction' => 'ASC']
			]
		);

		return $this->cachedProfiles[$cacheKey];
	}

	public function createProfile(int $userId, array $profile): int|string {
		$userId = $this->normalizeId($userId);
		if ($userId === null) {
			throw new \InvalidArgumentException('createProfile requires a valid user id.');
		}

		$name = trim((string)($profile['name'] ?? ''));
		if ($name === '') {
			throw new \InvalidArgumentException("createProfile requires profile['name'].");
		}

		$values = [
			'user_id' => $userId,
			'name' => $name,
			'profile' => (string)($profile['profile'] ?? ''),
			'standard' => !empty($profile['standard']) ? 1 : 0,
			'protected' => !empty($profile['protected']) ? 1 : 0,
			'active' => !empty($profile['active']) ? 1 : 0,
			'archive' => !empty($profile['archive']) ? 1 : 0
		];

		$insertId = $this->insertRow('base3system_sysprofile', $values);
		$this->clearCache();

		return $insertId ?? 0;
	}

	public function updateProfile(int|string $profileId, array $patch): void {
		$profileId = $this->normalizeId($profileId);
		if ($profileId === null) {
			throw new \InvalidArgumentException('updateProfile requires a valid profile id.');
		}

		$set = $this->filterProfilePatch($patch);
		if (empty($set)) {
			return;
		}

		$this->updateRows(
			'base3system_sysprofile',
			$set,
			$this->eq('base3system_sysprofile', 'id', $profileId),
			1
		);
		$this->clearCache();
	}

	public function archiveProfile(int|string $profileId): void {
		$this->updateProfile($profileId, ['archive' => 1, 'active' => 0]);
	}

	public function setActiveProfile(int $userId, int $profileId): void {
		$userId = $this->normalizeId($userId);
		$profileId = $this->normalizeId($profileId);

		if ($userId === null || $profileId === null) {
			throw new \InvalidArgumentException('setActiveProfile requires valid user and profile ids.');
		}

		$this->transaction([
			[
				'type' => 'update',
				'table' => 'base3system_sysprofile',
				'set' => ['active' => 0],
				'where' => $this->eq('base3system_sysprofile', 'user_id', $userId)
			],
			[
				'type' => 'update',
				'table' => 'base3system_sysprofile',
				'set' => ['active' => 1],
				'where' => $this->and([
					$this->eq('base3system_sysprofile', 'id', $profileId),
					$this->eq('base3system_sysprofile', 'user_id', $userId)
				]),
				'limit' => 1
			]
		]);

		$this->clearCache();
	}

	private function resolveUserId(?int $userId): ?int {
		if ($userId !== null) {
			return $this->normalizeId($userId);
		}

		$user = $this->usermanager->getUser();
		return $user && !empty($user->id) ? (int)$user->id : null;
	}

	private function profileFields(): array {
		return ['id', 'user_id', 'name', 'profile', 'standard', 'protected', 'active', 'archive'];
	}

	private function filterProfilePatch(array $patch): array {
		$set = [];

		foreach (['name', 'profile'] as $field) {
			if (array_key_exists($field, $patch)) {
				$set[$field] = (string)$patch[$field];
			}
		}

		foreach (['standard', 'protected', 'active', 'archive'] as $field) {
			if (array_key_exists($field, $patch)) {
				$set[$field] = !empty($patch[$field]) ? 1 : 0;
			}
		}

		return $set;
	}

	private function clearCache(): void {
		$this->cachedActiveProfile = null;
		$this->cachedProfiles = [];
	}
}
