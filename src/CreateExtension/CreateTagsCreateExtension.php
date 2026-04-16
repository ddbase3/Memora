<?php declare(strict_types=1);

namespace Memora\CreateExtension;

use Base3\Api\ISortable;
use Memora\Api\IMemoraCreateExtension;
use Memora\Api\IMemoraQueryService;

class CreateTagsCreateExtension implements IMemoraCreateExtension, ISortable {

	public function __construct(
		private readonly IMemoraQueryService $dataqueryservice
	) {}

	public function isApplicable(array $entry): bool {
		return !empty($entry['tags']) && is_array($entry['tags']);
	}

	public function beforeCreate(array &$entry, array &$context): void {
		if (!isset($entry['tags']) || !is_array($entry['tags'])) return;

		$tags = [];
		foreach ($entry['tags'] as $tag) {
			if (!is_string($tag)) continue;
			$tag = trim($tag);
			if ($tag === '') continue;
			$tags[$tag] = true;
		}

		$entry['tags'] = array_keys($tags);
	}

	public function create(array $entry, array &$context): void {
		if (empty($context['entry_id'])) {
			throw new \RuntimeException("Missing context['entry_id']. Ensure CreateBaseEntryCreateExtension ran first.");
		}

		$tags = $entry['tags'] ?? [];
		if (!is_array($tags) || empty($tags)) return;

		$existingTags = $this->loadExistingTagDescriptions($tags);
		$missingTags = array_values(array_diff($tags, $existingTags));

		if (!empty($missingTags)) {
			$now = date('Y-m-d H:i:s');
			$tagdescValues = [];

			foreach ($missingTags as $tag) {
				$tagdescValues[] = [
					'tag' => $tag,
					'description' => $tag,
					'created' => $now,
					'changed' => $now
				];
			}

			$context['transaction_queries'][] = [
				'type' => 'insert',
				'table' => 'base3system_systagdesc',
				'values' => $tagdescValues
			];
		}

		$tagValues = [];
		foreach ($tags as $tag) {
			if (!is_string($tag) || $tag === '') continue;
			$tagValues[] = [
				'entry_id' => (int)$context['entry_id'],
				'tag' => $tag
			];
		}

		if (empty($tagValues)) return;

		$context['transaction_queries'][] = [
			'type' => 'insert',
			'table' => 'base3system_systag',
			'values' => $tagValues
		];
	}

	public function afterCreate(array $entry, array &$context): void {}

	public function getPriority(): int {
		return 400;
	}

	private function loadExistingTagDescriptions(array $tags): array {
		$tags = array_values(array_unique(array_filter(array_map(
			static fn($tag): string => trim((string)$tag),
			$tags
		), static fn(string $tag): bool => $tag !== '')));

		if (empty($tags)) {
			return [];
		}

		$result = $this->dataqueryservice->executeQuery([
			'type' => 'select',
			'table' => 'base3system_systagdesc',
			'fields' => [
				[
					'element' => [
						'type' => 'fld',
						'table' => 'base3system_systagdesc',
						'field' => 'tag'
					],
					'alias' => 'tag'
				]
			],
			'where' => [
				'type' => 'op',
				'operator' => count($tags) === 1 ? '=' : 'IN',
				'params' => [
					[
						'type' => 'fld',
						'table' => 'base3system_systagdesc',
						'field' => 'tag'
					],
					count($tags) === 1 ? $tags[0] : $tags
				]
			]
		]);

		$existing = [];
		foreach (($result->rows ?? []) as $row) {
			if (empty($row['tag'])) continue;
			$existing[(string)$row['tag']] = true;
		}

		return array_keys($existing);
	}
}
