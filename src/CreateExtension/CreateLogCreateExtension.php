<?php declare(strict_types=1);

namespace Memora\CreateExtension;

use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3\Api\ISortable;
use Memora\Api\IMemoraCreateExtension;
use Memora\Api\IMemoraQueryService;

class CreateLogCreateExtension implements IMemoraCreateExtension, ISortable {

	public function __construct(
		private readonly IAccesscontrol $accesscontrol,
		private readonly IMemoraQueryService $dataqueryservice
	) {}

	public function isApplicable(array $entry): bool {
		return true;
	}

	public function beforeCreate(array &$entry, array &$context): void {}

	public function create(array $entry, array &$context): void {
		$entryId = (int)($context['entry_id'] ?? 0);
		if ($entryId <= 0) {
			throw new \RuntimeException("Missing context['entry_id']. Ensure CreateBaseEntryCreateExtension ran first.");
		}

		$userId = $this->resolveCurrentUserId();
		if ($userId <= 0) {
			return;
		}

		$datetime = !empty($entry['created']) && is_string($entry['created'])
			? $entry['created']
			: date('Y-m-d H:i:s');

		$values = [
			[
				'entry_id' => $entryId,
				'user_id' => $userId,
				'action' => 'new',
				'datetime' => $datetime
			]
		];

		if (!empty($entry['name']) && is_string($entry['name']) && trim($entry['name']) !== '') {
			$values[] = [
				'entry_id' => $entryId,
				'user_id' => $userId,
				'action' => 'rename',
				'datetime' => $datetime
			];
		}

		$context['transaction_queries'][] = [
			'type' => 'insert',
			'table' => 'base3system_syslog',
			'values' => $values
		];
	}

	public function afterCreate(array $entry, array &$context): void {}

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

	public function getPriority(): int {
		return 900;
	}
}
