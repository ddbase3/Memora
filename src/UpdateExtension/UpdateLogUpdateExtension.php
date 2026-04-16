<?php declare(strict_types=1);

namespace Memora\UpdateExtension;

use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3\Api\ISortable;
use Memora\Api\IMemoraQueryService;
use Memora\Api\IMemoraUpdateExtension;

class UpdateLogUpdateExtension implements IMemoraUpdateExtension, ISortable {

	public function __construct(
		private readonly IAccesscontrol $accesscontrol,
		private readonly IMemoraQueryService $dataqueryservice
	) {}

	public function isApplicable(array $patch): bool {
		return $this->hasSupportedOperation($patch);
	}

	public function beforeUpdate(array &$patch, array &$context): void {}

	public function update(array $patch, array &$context): void {
		$entryId = (int)($context['entry_id'] ?? 0);
		if ($entryId <= 0) {
			throw new \RuntimeException("Missing context['entry_id'] in update pipeline.");
		}

		$userId = $this->resolveCurrentUserId();
		if ($userId <= 0) {
			return;
		}

		$actions = $this->resolveActions($patch, $context);
		if (empty($actions)) {
			return;
		}

		$datetime = date('Y-m-d H:i:s');
		$values = [];

		foreach ($actions as $action) {
			$values[] = [
				'entry_id' => $entryId,
				'user_id' => $userId,
				'action' => $action,
				'datetime' => $datetime
			];
		}

		$context['transaction_queries'][] = [
			'type' => 'insert',
			'table' => 'base3system_syslog',
			'values' => $values
		];
	}

	public function afterUpdate(array $patch, array &$context): void {}

	private function resolveActions(array $patch, array $context): array {
		$actions = [];
		$currentEntry = $context['current_entry'] ?? [];

		if ($this->isRenamePatch($patch, $currentEntry)) {
			$actions['rename'] = true;
		}

		$archiveAction = $this->resolveArchiveAction($patch, $currentEntry);
		if ($archiveAction !== null) {
			$actions[$archiveAction] = true;
		}

		if ($this->hasSupportedOperation($patch)) {
			$actions['change'] = true;
		}

		return array_keys($actions);
	}

	private function isRenamePatch(array $patch, array $currentEntry): bool {
		if (array_key_exists('setname', $patch)) {
			$newName = trim((string)$patch['setname']);
			$currentName = trim((string)($currentEntry['name'] ?? ''));

			if ($newName !== '' && $newName !== $currentName) {
				return true;
			}
		}

		if (
			isset($patch['setdata'])
			&& is_array($patch['setdata'])
			&& array_key_exists('name', $patch['setdata'])
		) {
			$newTypedName = trim((string)$patch['setdata']['name']);
			$currentTypedName = trim((string)($currentEntry['data']['name'] ?? ''));

			if ($newTypedName !== '' && $newTypedName !== $currentTypedName) {
				return true;
			}
		}

		return false;
	}

	private function resolveArchiveAction(array $patch, array $currentEntry): ?string {
		if (
			!isset($patch['set'])
			|| !is_array($patch['set'])
			|| !array_key_exists('archive', $patch['set'])
		) {
			return null;
		}

		$currentArchive = $this->toBool($currentEntry['archive'] ?? false);
		$newArchive = $this->toBool($patch['set']['archive']);

		if ($currentArchive === $newArchive) {
			return null;
		}

		return $newArchive ? 'archive' : 'unarchive';
	}

	private function hasSupportedOperation(array $patch): bool {
		foreach ([
			'set',
			'setname',
			'setdata',
			'unsetdata',
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
			'replacegroupaccess',
			'setmetadata',
			'unsetmetadata'
		] as $key) {
			if (array_key_exists($key, $patch)) {
				return true;
			}
		}

		return false;
	}

	private function resolveCurrentUserId(): int {
		$identity = $this->accesscontrol->getUserId();

		if (is_int($identity)) {
			return $identity > 0 ? $identity : 0;
		}

		if (is_string($identity)) {
			$identity = trim($identity);
			if ($identity === '') {
				return 0;
			}

			if (ctype_digit($identity)) {
				return (int)$identity;
			}

			return $this->resolveUserIdByUsername($identity);
		}

		if (is_float($identity)) {
			return (int)$identity;
		}

		return 0;
	}

	private function resolveUserIdByUsername(string $username): int {
		$result = $this->dataqueryservice->executeQuery([
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
					$username
				]
			],
			'limit' => 1
		]);

		$row = $result->rows[0] ?? null;
		if (!is_array($row) || !isset($row['id'])) {
			return 0;
		}

		return (int)$row['id'];
	}

	private function toBool(mixed $value): bool {
		if (is_bool($value)) {
			return $value;
		}

		if (is_int($value) || is_float($value)) {
			return (int)$value !== 0;
		}

		if (is_string($value)) {
			$value = strtolower(trim($value));
			return in_array($value, ['1', 'true', 'yes', 'on'], true);
		}

		return !empty($value);
	}

	public function getPriority(): int {
		return 900;
	}
}
