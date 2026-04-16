<?php declare(strict_types=1);

namespace Memora\UpdateExtension;

use Base3\Api\ISortable;
use Memora\Api\IMemoraQueryService;
use Memora\Api\IMemoraUpdateExtension;

class UpdateTagsUpdateExtension implements IMemoraUpdateExtension, ISortable {

	public function __construct(
		private readonly IMemoraQueryService $dataqueryservice
	) {}

	public function isApplicable(array $patch): bool {
		return !empty($patch['addtags'])
			|| !empty($patch['removetags'])
			|| array_key_exists('replacetags', $patch);
	}

	public function beforeUpdate(array &$patch, array &$context): void {
		$hasReplace = array_key_exists('replacetags', $patch);
		$hasAddRemove = !empty($patch['addtags']) || !empty($patch['removetags']);

		if ($hasReplace && $hasAddRemove) {
			throw new \InvalidArgumentException("updateEntry patch must not combine replacetags with addtags/removetags.");
		}

		if (array_key_exists('replacetags', $patch)) {
			$patch['replacetags'] = $this->normalizeTags($patch['replacetags'], 'replacetags');
		}
		if (array_key_exists('addtags', $patch)) {
			$patch['addtags'] = $this->normalizeTags($patch['addtags'], 'addtags');
		}
		if (array_key_exists('removetags', $patch)) {
			$patch['removetags'] = $this->normalizeTags($patch['removetags'], 'removetags');
		}
	}

	public function update(array $patch, array &$context): void {
		$entryId = (int)($context['entry_id'] ?? 0);
		if ($entryId <= 0) {
			throw new \RuntimeException("Missing context['entry_id'] in update pipeline.");
		}

		if (array_key_exists('replacetags', $patch)) {
			$replace = $patch['replacetags'] ?? [];

			$context['transaction_queries'][] = [
				'type' => 'delete',
				'table' => 'base3system_systag',
				'where' => [
					'type' => 'op',
					'operator' => '=',
					'params' => [
						[ 'type' => 'fld', 'table' => 'base3system_systag', 'field' => 'entry_id' ],
						$entryId
					]
				]
			];

			$this->appendMissingTagdescQueries($replace, $context);

			if (!empty($replace)) {
				$values = [];
				foreach ($replace as $tag) {
					$values[] = [
						'entry_id' => $entryId,
						'tag' => $tag
					];
				}

				$context['transaction_queries'][] = [
					'type' => 'insert',
					'ignore' => true,
					'table' => 'base3system_systag',
					'values' => $values
				];
			}

			return;
		}

		$remove = $patch['removetags'] ?? [];
		if (!empty($remove)) {
			$context['transaction_queries'][] = [
				'type' => 'delete',
				'table' => 'base3system_systag',
				'where' => [
					'type' => 'op',
					'operator' => 'AND',
					'params' => [
						[
							'type' => 'op',
							'operator' => '=',
							'params' => [
								[ 'type' => 'fld', 'table' => 'base3system_systag', 'field' => 'entry_id' ],
								$entryId
							]
						],
						[
							'type' => 'op',
							'operator' => 'IN',
							'params' => [
								[ 'type' => 'fld', 'table' => 'base3system_systag', 'field' => 'tag' ],
								$remove
							]
						]
					]
				]
			];
		}

		$add = $patch['addtags'] ?? [];
		if (!empty($add)) {
			$this->appendMissingTagdescQueries($add, $context);

			$values = [];
			foreach ($add as $tag) {
				$values[] = [
					'entry_id' => $entryId,
					'tag' => $tag
				];
			}

			$context['transaction_queries'][] = [
				'type' => 'insert',
				'ignore' => true,
				'table' => 'base3system_systag',
				'values' => $values
			];
		}
	}

	public function afterUpdate(array $patch, array &$context): void {}

	private function appendMissingTagdescQueries(array $tags, array &$context): void {
		$tags = $this->normalizeTagList($tags);
		if (empty($tags)) {
			return;
		}

		$existingTags = $this->loadExistingTagDescriptions($tags);
		$missingTags = array_values(array_diff($tags, $existingTags));

		if (empty($missingTags)) {
			return;
		}

		$now = date('Y-m-d H:i:s');
		$values = [];

		foreach ($missingTags as $tag) {
			$values[] = [
				'tag' => $tag,
				'description' => $tag,
				'created' => $now,
				'changed' => $now
			];
		}

		$context['transaction_queries'][] = [
			'type' => 'insert',
			'ignore' => true,
			'table' => 'base3system_systagdesc',
			'values' => $values
		];
	}

	private function loadExistingTagDescriptions(array $tags): array {
		$tags = $this->normalizeTagList($tags);
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

	private function normalizeTags(mixed $value, string $key): array {
		if (!is_array($value)) {
			throw new \InvalidArgumentException("updateEntry patch['" . $key . "'] must be an array.");
		}

		return $this->normalizeTagList($value);
	}

	private function normalizeTagList(array $tags): array {
		$normalized = [];

		foreach ($tags as $tag) {
			if (!is_string($tag)) continue;

			$tag = trim($tag);
			if ($tag === '') continue;

			$normalized[$tag] = true;
		}

		return array_keys($normalized);
	}

	public function getPriority(): int {
		return 300;
	}
}
