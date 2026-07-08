<?php declare(strict_types=1);

namespace Memora\Service;

use Base3\Usermanager\Api\IUsermanager;
use Memora\Api\IMemoraQueryService;
use ResourceFoundation\Api\IEntityUserDataService;

class MemoraUserDataService extends AbstractMemoraTableService implements IEntityUserDataService {

	public function __construct(
		IMemoraQueryService $dataqueryservice,
		private readonly IUsermanager $usermanager
	) {
		parent::__construct($dataqueryservice);
	}

	public function getUserData(int|string $entryId, ?int $userId = null): array {
		$entryId = $this->requireId($entryId, 'entry');
		$userId = $this->resolveUserId($userId);

		$rows = $this->fetchRows(
			'base3system_sysentryuserdata',
			['user_id', 'entry_id', 'name', 'value'],
			$this->and([
				$this->eq('base3system_sysentryuserdata', 'entry_id', $entryId),
				$this->eq('base3system_sysentryuserdata', 'user_id', $userId)
			]),
			[['element' => $this->fld('base3system_sysentryuserdata', 'name'), 'direction' => 'ASC']]
		);

		$result = [];
		foreach ($rows as $row) {
			$name = (string)($row['name'] ?? '');
			if ($name === '') continue;
			$result[$name] = $this->decodeValue($row['value'] ?? null);
		}

		return $result;
	}

	public function getUserDataValue(int|string $entryId, string $name, mixed $default = null, ?int $userId = null): mixed {
		$name = trim($name);
		if ($name === '') {
			return $default;
		}

		$data = $this->getUserData($entryId, $userId);
		return array_key_exists($name, $data) ? $data[$name] : $default;
	}

	public function setUserData(int|string $entryId, array $data, ?int $userId = null): void {
		$entryId = $this->requireId($entryId, 'entry');
		$userId = $this->resolveUserId($userId);
		$queries = [];

		foreach ($data as $name => $value) {
			if (!is_string($name)) continue;
			$name = trim($name);
			if ($name === '') continue;

			$encoded = $this->encodeValue($value);
			if ($this->rowExists($entryId, $userId, $name)) {
				$queries[] = [
					'type' => 'update',
					'table' => 'base3system_sysentryuserdata',
					'set' => ['value' => $encoded],
					'where' => $this->userDataWhere($entryId, $userId, $name),
					'limit' => 1
				];
				continue;
			}

			$queries[] = [
				'type' => 'insert',
				'table' => 'base3system_sysentryuserdata',
				'values' => [[
					'user_id' => $userId,
					'entry_id' => $entryId,
					'name' => $name,
					'value' => $encoded
				]]
			];
		}

		$this->transaction($queries);
	}

	public function removeUserData(int|string $entryId, array $names, ?int $userId = null): void {
		$entryId = $this->requireId($entryId, 'entry');
		$userId = $this->resolveUserId($userId);
		$names = $this->normalizeStrings($names);

		if (empty($names)) {
			return;
		}

		$this->deleteRows('base3system_sysentryuserdata', $this->and([
			$this->eq('base3system_sysentryuserdata', 'entry_id', $entryId),
			$this->eq('base3system_sysentryuserdata', 'user_id', $userId),
			$this->in('base3system_sysentryuserdata', 'name', $names)
		]));
	}

	private function rowExists(int $entryId, int $userId, string $name): bool {
		$row = $this->fetchRow('base3system_sysentryuserdata', ['entry_id'], $this->userDataWhere($entryId, $userId, $name));
		return $row !== null;
	}

	private function userDataWhere(int $entryId, int $userId, string $name): array {
		return $this->and([
			$this->eq('base3system_sysentryuserdata', 'entry_id', $entryId),
			$this->eq('base3system_sysentryuserdata', 'user_id', $userId),
			$this->eq('base3system_sysentryuserdata', 'name', $name)
		]);
	}

	private function resolveUserId(?int $userId): int {
		if ($userId !== null) {
			$id = $this->normalizeId($userId);
			if ($id !== null) return $id;
		}

		$user = $this->usermanager->getUser();
		if (!$user || empty($user->id)) {
			throw new \RuntimeException('No current user available for entity user data.');
		}

		return (int)$user->id;
	}

	private function encodeValue(mixed $value): string {
		if (is_string($value)) {
			return $value;
		}

		$json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($json === false) {
			throw new \RuntimeException('Failed to encode user data value.');
		}

		return $json;
	}

	private function decodeValue(mixed $value): mixed {
		if (!is_string($value)) {
			return $value;
		}

		$trimmed = trim($value);
		if ($trimmed === '') {
			return $value;
		}

		$decoded = json_decode($value, true);
		return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
	}

	private function requireId(int|string $id, string $name): int {
		$id = $this->normalizeId($id);
		if ($id === null) {
			throw new \InvalidArgumentException('Invalid ' . $name . ' id.');
		}

		return $id;
	}
}
