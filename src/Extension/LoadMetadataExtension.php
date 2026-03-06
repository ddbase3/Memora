<?php declare(strict_types=1);

namespace Memora\Extension;

use Base3\Api\ISortable;
use Memora\Api\IMemoraQueryExtension;
use Memora\Api\IMemoraQueryService;

class LoadMetadataExtension implements IMemoraQueryExtension, ISortable {

	public function __construct(
		private readonly IMemoraQueryService $dataqueryservice
	) {}

	public function isApplicable(array $options): bool {
		return !empty($options['loadmetadata']);
	}

	public function applyToQuery(array $query, array $options): array {
		// No additional select fields needed. Metadata is loaded in processResult().
		return $query;
	}

	public function processResult(array $rows, array $options): array {
		if (empty($rows)) {
			return $rows;
		}

		$ids = [];
		foreach ($rows as $row) {
			if (isset($row['id'])) {
				$ids[] = $row['id'];
			}
		}
		$ids = array_values(array_unique($ids));
		if (empty($ids)) {
			return $rows;
		}

		$metaQuery = [
			'type' => 'select',
			'fields' => [
				[ 'element' => [ 'type' => 'fld', 'table' => 'base3system_sysmetadata', 'field' => 'entry_id' ], 'alias' => 'entry_id' ],
				[ 'element' => [ 'type' => 'fld', 'table' => 'base3system_sysmetadata', 'field' => 'name' ], 'alias' => 'name' ],
				[ 'element' => [ 'type' => 'fld', 'table' => 'base3system_sysmetadata', 'field' => 'data' ], 'alias' => 'data' ]
			],
			'table' => 'base3system_sysmetadata',
			'where' => [
				'type' => 'op',
				'operator' => 'IN',
				'params' => [
					[ 'type' => 'fld', 'table' => 'base3system_sysmetadata', 'field' => 'entry_id' ],
					$ids
				]
			]
		];

		$metaResult = $this->dataqueryservice->executeQuery($metaQuery);
		$metaRows = $metaResult->rows ?? [];
		if (empty($metaRows)) {
			foreach ($rows as &$row) {
				$row['metadata'] = $row['metadata'] ?? [];
			}
			unset($row);
			return $rows;
		}

		$metaByEntry = [];
		foreach ($metaRows as $m) {
			$entryId = $m['entry_id'] ?? null;
			$name = $m['name'] ?? null;
			if (!$entryId || !$name) continue;

			$raw = (string)($m['data'] ?? '');
			$decoded = $this->decodeMetadataValue($raw);

			$metaByEntry[$entryId][$name] = $decoded;
		}

		foreach ($rows as &$row) {
			$id = $row['id'] ?? null;
			$row['metadata'] = $metaByEntry[$id] ?? [];
		}
		unset($row);

		return $rows;
	}

	private function decodeMetadataValue(string $raw): mixed {
		$raw = trim($raw);
		if ($raw === '') {
			return null;
		}

		$decoded = json_decode($raw, true);
		if (json_last_error() === JSON_ERROR_NONE) {
			return $decoded;
		}

		return $raw;
	}

	public function getPriority(): int {
		return 995;
	}
}
