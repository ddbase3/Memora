<?php declare(strict_types=1);

namespace Memora\Service;

use Memora\Api\IMemoraQueryService;
use ResourceFoundation\Api\IEntityDataService;
use ResourceFoundation\Api\IEntityRelationService;

class MemoraRelationService extends AbstractMemoraTableService implements IEntityRelationService {

	public function __construct(
		IMemoraQueryService $dataqueryservice,
		private readonly IEntityDataService $entitydataservice
	) {
		parent::__construct($dataqueryservice);
	}

	public function getRelations(int|string $entryId): array {
		$entryId = $this->requireId($entryId, 'entry');
		return $this->fetchRows(
			'base3system_sysallocview',
			['id', 'entry_id', 'peer_id'],
			$this->eq('base3system_sysallocview', 'entry_id', $entryId),
			[['element' => $this->fld('base3system_sysallocview', 'peer_id'), 'direction' => 'ASC']]
		);
	}

	public function getRelationIds(int|string $entryId): array {
		return $this->normalizeIds(array_column($this->getRelations($entryId), 'peer_id'));
	}

	public function addRelations(int|string $entryId, array $peerIds): void {
		$peerIds = $this->normalizeIds($peerIds);
		if (empty($peerIds)) {
			return;
		}

		$this->entitydataservice->updateEntry($entryId, ['addallocs' => $peerIds]);
	}

	public function removeRelations(int|string $entryId, array $peerIds): void {
		$peerIds = $this->normalizeIds($peerIds);
		if (empty($peerIds)) {
			return;
		}

		$this->entitydataservice->updateEntry($entryId, ['removeallocs' => $peerIds]);
	}

	public function replaceRelations(int|string $entryId, array $peerIds): void {
		$this->entitydataservice->updateEntry($entryId, [
			'replaceallocs' => $this->normalizeIds($peerIds)
		]);
	}

	private function requireId(int|string $id, string $name): int {
		$id = $this->normalizeId($id);
		if ($id === null) {
			throw new \InvalidArgumentException('Invalid ' . $name . ' id.');
		}

		return $id;
	}
}
