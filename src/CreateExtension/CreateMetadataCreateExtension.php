<?php declare(strict_types=1);

namespace Memora\CreateExtension;

use Base3\Api\ISortable;
use Memora\Api\IMemoraCreateExtension;

class CreateMetadataCreateExtension implements IMemoraCreateExtension, ISortable {

	public function isApplicable(array $entry): bool {
		return !empty($entry['metadata']) && is_array($entry['metadata']);
	}

	public function beforeCreate(array &$entry, array &$context): void {
		if (!isset($entry['metadata']) || !is_array($entry['metadata'])) return;

		$normalized = [];

		foreach ($entry['metadata'] as $name => $value) {
			if (!is_string($name)) continue;

			$name = trim($name);
			if ($name === '') continue;
			if ($value === null) continue;

			$normalized[$name] = $this->encodeMetadataValue($value);
		}

		$entry['metadata'] = $normalized;
	}

	public function create(array $entry, array &$context): void {
		if (empty($context['entry_id'])) {
			throw new \RuntimeException("Missing context['entry_id']. Ensure CreateBaseEntryCreateExtension ran first.");
		}

		$metadata = $entry['metadata'] ?? [];
		if (!is_array($metadata) || empty($metadata)) return;

		$values = [];

		foreach ($metadata as $name => $encoded) {
			if (!is_string($name) || $name === '') continue;
			if (!is_string($encoded)) continue;

			$values[] = [
				'entry_id' => (int)$context['entry_id'],
				'name' => $name,
				'data' => $encoded
			];
		}

		if (empty($values)) return;

		$context['transaction_queries'][] = [
			'type' => 'insert',
			'table' => 'base3system_sysmetadata',
			'values' => $values
		];
	}

	public function afterCreate(array $entry, array &$context): void {}

	private function encodeMetadataValue(mixed $value): string {
		if (is_string($value)) {
			return $value;
		}

		$json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($json === false) {
			throw new \RuntimeException("Failed to encode metadata value.");
		}

		return $json;
	}

	public function getPriority(): int {
		return 750;
	}
}
