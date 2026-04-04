<?php declare(strict_types=1);

namespace Memora\CreateExtension;

use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3\Api\ISortable;
use Memora\Api\IMemoraCreateExtension;
use Memora\Api\IMemoraQueryService;

class CreateUserAccessCreateExtension implements IMemoraCreateExtension, ISortable {

	private const MODE_PRIORITY = [
		'visitor' => 100,
		'moderator' => 200,
		'owner' => 300
	];

	public function __construct(
		private readonly IMemoraQueryService $dataqueryservice,
		private readonly IAccesscontrol $accesscontrol
	) {}

	public function isApplicable(array $entry): bool {
		return true;
	}

	public function beforeCreate(array &$entry, array &$context): void {
		$currentUserId = $this->resolveCurrentUserId();

		$list = $entry['useraccess'] ?? [];
		if (!is_array($list)) {
			$list = [];
		}

		$list[] = [
			'user_id' => $currentUserId,
			'mode' => 'owner'
		];

		$normalized = [];

		foreach ($list as $ua) {
			if (!is_array($ua)) continue;

			$userId = $this->normalizeEntryUserId($ua['user_id'] ?? null);
			$mode = $this->normalizeMode($ua['mode'] ?? null);

			if ($userId === null || $mode === null) continue;

			if (
				!isset($normalized[$userId]) ||
				$this->isModeHigher($mode, $normalized[$userId]['mode'])
			) {
				$normalized[$userId] = [
					'user_id' => $userId,
					'mode' => $mode
				];
			}
		}

		$entry['useraccess'] = array_values($normalized);
	}

	public function create(array $entry, array &$context): void {
		if (empty($context['entry_id'])) {
			throw new \RuntimeException("Missing context['entry_id']. Ensure CreateBaseEntryCreateExtension ran first.");
		}

		$list = $entry['useraccess'] ?? [];
		if (!is_array($list) || empty($list)) return;

		$values = [];

		foreach ($list as $ua) {
			if (!is_array($ua)) continue;

			$userId = $this->normalizeEntryUserId($ua['user_id'] ?? null);
			$mode = $this->normalizeMode($ua['mode'] ?? null);

			if ($userId === null || $mode === null) continue;

			$values[] = [
				'entry_id' => (int)$context['entry_id'],
				'user_id' => $userId,
				'mode' => $mode
			];
		}

		if (empty($values)) return;

		$context['transaction_queries'][] = [
			'type' => 'insert',
			'table' => 'base3system_sysuseraccess',
			'values' => $values
		];
	}

	public function afterCreate(array $entry, array &$context): void {}

	private function resolveCurrentUserId(): int {
		$userIdentifier = $this->accesscontrol->getUserId();

		if (is_int($userIdentifier) && $userIdentifier > 0) {
			return $userIdentifier;
		}

		if (!is_string($userIdentifier)) {
			throw new \RuntimeException("Unable to resolve current user identifier.");
		}

		$userIdentifier = trim($userIdentifier);
		if ($userIdentifier === '') {
			throw new \RuntimeException("Unable to resolve current user identifier.");
		}
		if ($userIdentifier === 'internal') {
			throw new \RuntimeException("Internal user cannot be assigned as entry owner.");
		}

		$query = [
			'type' => 'select',
			'table' => 'base3system_sysuser',
			'fields' => [
				[
					'element' => [
						'type' => 'fld',
						'table' => 'base3system_sysuser',
						'field' => 'id'
					],
					'alias' => 'id'
				]
			],
			'where' => [
				'type' => 'op',
				'operator' => '=',
				'params' => [
					[
						'type' => 'fld',
						'table' => 'base3system_sysuser',
						'field' => 'name'
					],
					$userIdentifier
				]
			],
			'limit' => 1
		];

		$result = $this->dataqueryservice->executeQuery($query);
		$row = $result->rows[0] ?? null;

		if (!is_array($row)) {
			throw new \RuntimeException("Current user '" . $userIdentifier . "' was not found in base3system_sysuser.");
		}

		$userId = $this->normalizeEntryUserId($row['id'] ?? null);
		if ($userId === null) {
			throw new \RuntimeException("Current user '" . $userIdentifier . "' does not have a valid numeric user id.");
		}

		return $userId;
	}

	private function normalizeEntryUserId(mixed $userId): ?int {
		if (is_string($userId) && ctype_digit($userId)) {
			$userId = (int)$userId;
		}

		if (!is_int($userId) || $userId <= 0) {
			return null;
		}

		return $userId;
	}

	private function normalizeMode(mixed $mode): ?string {
		if (!is_string($mode)) {
			return null;
		}

		$mode = trim($mode);
		if ($mode === '') {
			return null;
		}

		if (!isset(self::MODE_PRIORITY[$mode])) {
			return null;
		}

		return $mode;
	}

	private function isModeHigher(string $left, string $right): bool {
		return self::MODE_PRIORITY[$left] > self::MODE_PRIORITY[$right];
	}

	public function getPriority(): int {
		return 600;
	}
}
