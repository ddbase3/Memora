<?php declare(strict_types=1);

namespace Memora\CreateExtension;

use Base3\Api\ISortable;
use Memora\Api\IMemoraCreateExtension;
use Memora\Api\IMemoraQueryService;

class CreateBaseEntryCreateExtension implements IMemoraCreateExtension, ISortable {

	public function __construct(private readonly IMemoraQueryService $dataqueryservice) {}

	public function isApplicable(array $entry): bool {
		return true;
	}

	public function beforeCreate(array &$entry, array &$context): void {
		if (empty($entry['type']) || !is_string($entry['type'])) {
			throw new \InvalidArgumentException("createEntry requires entry['type'] as string.");
		}
		if (empty($context['type_id'])) {
			throw new \RuntimeException("Missing context['type_id']. Ensure CreateTypeResolverCreateExtension runs first.");
		}

		if (empty($entry['uuid']) || !is_string($entry['uuid'])) {
			$entry['uuid'] = bin2hex(random_bytes(16));
		}
		if (empty($entry['etag']) || !is_string($entry['etag'])) {
			$entry['etag'] = bin2hex(random_bytes(16));
		}

		$entry['archive'] = 0;
		$entry['dellock'] = 0;

		if (!isset($entry['connections'])) {
			$entry['connections'] = 0;
		}

		$now = date('Y-m-d H:i:s');
		$entry['created'] = $now;
		$entry['changed'] = $now;
	}

	public function create(array $entry, array &$context): void {
		$query = [
			'type' => 'insert',
			'table' => 'base3system_sysentry',
			'values' => [[
				'uuid' => [
					'type' => 'fn',
					'function' => 'UNHEX',
					'params' => [ (string)$entry['uuid'] ]
				],
				'type_id' => (int)$context['type_id'],
				'archive' => 0,
				'dellock' => 0,
				'connections' => (int)($entry['connections'] ?? 0),
				'etag' => [
					'type' => 'fn',
					'function' => 'UNHEX',
					'params' => [ (string)$entry['etag'] ]
				],
				'created' => (string)$entry['created'],
				'changed' => (string)$entry['changed']
			]]
		];

		$result = $this->dataqueryservice->executeQuery($query);

		if ($result->insertId === null) {
			throw new \RuntimeException("Insert into base3system_sysentry did not return insertId.");
		}

		$context['entry_id'] = $result->insertId;
	}

	public function afterCreate(array $entry, array &$context): void {}

	public function getPriority(): int {
		return 200;
	}
}
