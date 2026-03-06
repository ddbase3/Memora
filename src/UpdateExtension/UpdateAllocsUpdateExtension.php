<?php declare(strict_types=1);

namespace Memora\UpdateExtension;

use Base3\Api\ISortable;
use Memora\Api\IMemoraUpdateExtension;

class UpdateAllocsUpdateExtension implements IMemoraUpdateExtension, ISortable {

	public function isApplicable(array $patch): bool {
		return !empty($patch['addallocs'])
			|| !empty($patch['removeallocs'])
			|| array_key_exists('replaceallocs', $patch);
	}

	public function beforeUpdate(array &$patch, array &$context): void {
		$hasReplace = array_key_exists('replaceallocs', $patch);
		$hasAddRemove = !empty($patch['addallocs']) || !empty($patch['removeallocs']);

		if ($hasReplace && $hasAddRemove) {
			throw new \InvalidArgumentException("updateEntry patch must not combine replaceallocs with addallocs/removeallocs.");
		}

		$entryId = (int)($context['entry_id'] ?? 0);
		if ($entryId <= 0) {
			throw new \RuntimeException("Missing context['entry_id'] in update pipeline.");
		}

		if (array_key_exists('replaceallocs', $patch)) {
			$patch['replaceallocs'] = $this->normalizeAllocs($patch['replaceallocs'], $entryId, 'replaceallocs');
		}
		if (array_key_exists('addallocs', $patch)) {
			$patch['addallocs'] = $this->normalizeAllocs($patch['addallocs'], $entryId, 'addallocs');
		}
		if (array_key_exists('removeallocs', $patch)) {
			$patch['removeallocs'] = $this->normalizePeerIds($patch['removeallocs'], $entryId, 'removeallocs');
		}
	}

	public function update(array $patch, array &$context): void {
		$entryId = (int)($context['entry_id'] ?? 0);
		if ($entryId <= 0) {
			throw new \RuntimeException("Missing context['entry_id'] in update pipeline.");
		}

		if (array_key_exists('replaceallocs', $patch)) {
			$context['transaction_queries'][] = [
				'type' => 'delete',
				'table' => 'base3system_sysalloc',
				'where' => [
					'type' => 'op',
					'operator' => 'OR',
					'params' => [
						[
							'type' => 'op',
							'operator' => '=',
							'params' => [
								[ 'type' => 'fld', 'table' => 'base3system_sysalloc', 'field' => 'entry_id_1' ],
								$entryId
							]
						],
						[
							'type' => 'op',
							'operator' => '=',
							'params' => [
								[ 'type' => 'fld', 'table' => 'base3system_sysalloc', 'field' => 'entry_id_2' ],
								$entryId
							]
						]
					]
				]
			];

			$replace = $patch['replaceallocs'] ?? [];
			if (!empty($replace)) {
				$values = [];
				foreach ($replace as $pair) {
					$values[] = [
						'entry_id_1' => $pair['entry_id_1'],
						'entry_id_2' => $pair['entry_id_2']
					];
				}

				$context['transaction_queries'][] = [
					'type' => 'insert',
					'ignore' => true,
					'table' => 'base3system_sysalloc',
					'values' => $values
				];
			}

			return;
		}

		$remove = $patch['removeallocs'] ?? [];
		if (!empty($remove)) {
			$or = [];

			foreach ($remove as $peerId) {
				$or[] = [
					'type' => 'op',
					'operator' => 'OR',
					'params' => [
						[
							'type' => 'op',
							'operator' => 'AND',
							'params' => [
								[
									'type' => 'op',
									'operator' => '=',
									'params' => [
										[ 'type' => 'fld', 'table' => 'base3system_sysalloc', 'field' => 'entry_id_1' ],
										$entryId
									]
								],
								[
									'type' => 'op',
									'operator' => '=',
									'params' => [
										[ 'type' => 'fld', 'table' => 'base3system_sysalloc', 'field' => 'entry_id_2' ],
										$peerId
									]
								]
							]
						],
						[
							'type' => 'op',
							'operator' => 'AND',
							'params' => [
								[
									'type' => 'op',
									'operator' => '=',
									'params' => [
										[ 'type' => 'fld', 'table' => 'base3system_sysalloc', 'field' => 'entry_id_1' ],
										$peerId
									]
								],
								[
									'type' => 'op',
									'operator' => '=',
									'params' => [
										[ 'type' => 'fld', 'table' => 'base3system_sysalloc', 'field' => 'entry_id_2' ],
										$entryId
									]
								]
							]
						]
					]
				];
			}

			$context['transaction_queries'][] = [
				'type' => 'delete',
				'table' => 'base3system_sysalloc',
				'where' => [
					'type' => 'op',
					'operator' => 'OR',
					'params' => $or
				]
			];
		}

		$add = $patch['addallocs'] ?? [];
		if (!empty($add)) {
			$values = [];
			foreach ($add as $pair) {
				$values[] = [
					'entry_id_1' => $pair['entry_id_1'],
					'entry_id_2' => $pair['entry_id_2']
				];
			}

			$context['transaction_queries'][] = [
				'type' => 'insert',
				'ignore' => true,
				'table' => 'base3system_sysalloc',
				'values' => $values
			];
		}
	}

	public function afterUpdate(array $patch, array &$context): void {}

	private function normalizeAllocs(mixed $value, int $entryId, string $key): array {
		if (!is_array($value)) {
			throw new \InvalidArgumentException("updateEntry patch['" . $key . "'] must be an array.");
		}

		$normalized = [];

		foreach ($value as $peerId) {
			if (is_string($peerId) && ctype_digit($peerId)) {
				$peerId = (int)$peerId;
			}
			if (!is_int($peerId) || $peerId <= 0 || $peerId === $entryId) {
				continue;
			}

			$pair = $this->makeCanonicalPair($entryId, $peerId);
			$normalized[$pair['entry_id_1'] . ':' . $pair['entry_id_2']] = $pair;
		}

		return array_values($normalized);
	}

	private function normalizePeerIds(mixed $value, int $entryId, string $key): array {
		if (!is_array($value)) {
			throw new \InvalidArgumentException("updateEntry patch['" . $key . "'] must be an array.");
		}

		$normalized = [];

		foreach ($value as $peerId) {
			if (is_string($peerId) && ctype_digit($peerId)) {
				$peerId = (int)$peerId;
			}
			if (!is_int($peerId) || $peerId <= 0 || $peerId === $entryId) {
				continue;
			}

			$normalized[$peerId] = $peerId;
		}

		return array_values($normalized);
	}

	private function makeCanonicalPair(int $entryId, int $peerId): array {
		if ($entryId < $peerId) {
			return [
				'entry_id_1' => $entryId,
				'entry_id_2' => $peerId
			];
		}

		return [
			'entry_id_1' => $peerId,
			'entry_id_2' => $entryId
		];
	}

	public function getPriority(): int {
		return 400;
	}
}
