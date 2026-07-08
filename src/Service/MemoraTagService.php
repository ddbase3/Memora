<?php declare(strict_types=1);

namespace Memora\Service;

use Memora\Api\IMemoraQueryService;
use ResourceFoundation\Api\IEntityDataService;
use ResourceFoundation\Api\IEntityTagService;

class MemoraTagService extends AbstractMemoraTableService implements IEntityTagService {

	public function __construct(
		IMemoraQueryService $dataqueryservice,
		private readonly IEntityDataService $entitydataservice
	) {
		parent::__construct($dataqueryservice);
	}

	public function getEntryTags(int|string $entryId): array {
		$entryId = $this->requireId($entryId, 'entry');
		$rows = $this->fetchRows(
			'base3system_systag',
			['entry_id', 'tag'],
			$this->eq('base3system_systag', 'entry_id', $entryId),
			[['element' => $this->fld('base3system_systag', 'tag'), 'direction' => 'ASC']]
		);

		return $this->normalizeStrings(array_column($rows, 'tag'));
	}

	public function addEntryTags(int|string $entryId, array $tags): void {
		$tags = $this->normalizeStrings($tags);
		if (empty($tags)) {
			return;
		}

		$this->entitydataservice->updateEntry($entryId, ['addtags' => $tags]);
	}

	public function removeEntryTags(int|string $entryId, array $tags): void {
		$tags = $this->normalizeStrings($tags);
		if (empty($tags)) {
			return;
		}

		$this->entitydataservice->updateEntry($entryId, ['removetags' => $tags]);
	}

	public function replaceEntryTags(int|string $entryId, array $tags): void {
		$this->entitydataservice->updateEntry($entryId, [
			'replacetags' => $this->normalizeStrings($tags)
		]);
	}

	public function getTags(?string $scope = null): array {
		$scope = $scope !== null ? trim($scope) : null;

		if ($scope === null || $scope === '') {
			return $this->fetchRows(
				'base3system_systagdesc',
				['tag', 'description', 'created', 'changed'],
				null,
				[['element' => $this->fld('base3system_systagdesc', 'tag'), 'direction' => 'ASC']]
			);
		}

		$rows = $this->fetchRows(
			'base3system_systagscope',
			['tag', 'scope'],
			$this->eq('base3system_systagscope', 'scope', $scope),
			[['element' => $this->fld('base3system_systagscope', 'tag'), 'direction' => 'ASC']]
		);

		$tags = $this->normalizeStrings(array_column($rows, 'tag'));
		if (empty($tags)) {
			return [];
		}

		return $this->fetchRows(
			'base3system_systagdesc',
			['tag', 'description', 'created', 'changed'],
			$this->in('base3system_systagdesc', 'tag', $tags),
			[['element' => $this->fld('base3system_systagdesc', 'tag'), 'direction' => 'ASC']]
		);
	}

	public function describeTag(string $tag, string $description): void {
		$tag = $this->requireString($tag, 'tag');
		$existing = $this->fetchRow('base3system_systagdesc', ['tag'], $this->eq('base3system_systagdesc', 'tag', $tag));

		if ($existing) {
			$this->updateRows(
				'base3system_systagdesc',
				['description' => $description, 'changed' => $this->now()],
				$this->eq('base3system_systagdesc', 'tag', $tag),
				1
			);
			return;
		}

		$this->insertRow('base3system_systagdesc', [
			'tag' => $tag,
			'description' => $description,
			'created' => $this->now(),
			'changed' => $this->now()
		], true);
	}

	public function assignTagToScope(string $tag, string $scope): void {
		$this->insertRow('base3system_systagscope', [
			'tag' => $this->requireString($tag, 'tag'),
			'scope' => $this->requireString($scope, 'scope')
		], true);
	}

	public function removeTagFromScope(string $tag, string $scope): void {
		$this->deleteRows('base3system_systagscope', $this->and([
			$this->eq('base3system_systagscope', 'tag', $this->requireString($tag, 'tag')),
			$this->eq('base3system_systagscope', 'scope', $this->requireString($scope, 'scope'))
		]));
	}

	public function assignTagToModule(string $tag, string $module): void {
		$this->insertRow('base3system_sysmoduletag', [
			'tag' => $this->requireString($tag, 'tag'),
			'module' => $this->requireString($module, 'module')
		], true);
	}

	public function removeTagFromModule(string $tag, string $module): void {
		$this->deleteRows('base3system_sysmoduletag', $this->and([
			$this->eq('base3system_sysmoduletag', 'tag', $this->requireString($tag, 'tag')),
			$this->eq('base3system_sysmoduletag', 'module', $this->requireString($module, 'module'))
		]));
	}

	private function requireId(int|string $id, string $name): int {
		$id = $this->normalizeId($id);
		if ($id === null) {
			throw new \InvalidArgumentException('Invalid ' . $name . ' id.');
		}

		return $id;
	}

	private function requireString(string $value, string $name): string {
		$value = trim($value);
		if ($value === '') {
			throw new \InvalidArgumentException('Missing ' . $name . '.');
		}

		return $value;
	}
}
