<?php declare(strict_types=1);

namespace Memora\Service;

use Base3\Usermanager\Api\IUsermanager;
use Memora\Api\IMemoraQueryService;
use ResourceFoundation\Api\IEntityActivityService;

class MemoraActivityService extends AbstractMemoraTableService implements IEntityActivityService {

	public function __construct(
		IMemoraQueryService $dataqueryservice,
		private readonly IUsermanager $usermanager
	) {
		parent::__construct($dataqueryservice);
	}

	public function getLogs(int|string $entryId, array $options = []): array {
		$entryId = $this->requireId($entryId, 'entry');
		$limit = isset($options['limit']) ? max(1, (int)$options['limit']) : null;
		$direction = !empty($options['reverse']) ? 'DESC' : 'ASC';

		return $this->fetchRows(
			'base3system_syslog',
			['id', 'entry_id', 'user_id', 'action', 'datetime'],
			$this->eq('base3system_syslog', 'entry_id', $entryId),
			[['element' => $this->fld('base3system_syslog', 'datetime'), 'direction' => $direction]],
			$limit
		);
	}

	public function addLog(int|string $entryId, string $action, ?int $userId = null): void {
		$entryId = $this->requireId($entryId, 'entry');
		$action = trim($action);
		if ($action === '') {
			throw new \InvalidArgumentException('addLog requires a non-empty action.');
		}

		$this->insertRow('base3system_syslog', [
			'entry_id' => $entryId,
			'user_id' => $this->resolveUserId($userId),
			'action' => $action,
			'datetime' => $this->now()
		]);
	}

	public function getComments(int|string $entryId, array $options = []): array {
		$entryId = $this->requireId($entryId, 'entry');
		$where = [$this->eq('base3system_syscomment', 'entry_id', $entryId)];

		if (array_key_exists('parent_id', $options)) {
			$parentId = $this->normalizeId($options['parent_id']);
			if ($parentId !== null) {
				$where[] = $this->eq('base3system_syscomment', 'parent_id', $parentId);
			}
		}

		$limit = isset($options['limit']) ? max(1, (int)$options['limit']) : null;
		$direction = !empty($options['reverse']) ? 'DESC' : 'ASC';

		return $this->fetchRows(
			'base3system_syscomment',
			['id', 'parent_id', 'entry_id', 'comment', 'user_id', 'datetime'],
			$this->and($where),
			[['element' => $this->fld('base3system_syscomment', 'datetime'), 'direction' => $direction]],
			$limit
		);
	}

	public function addComment(int|string $entryId, string $comment, ?int $parentId = null): int|string {
		$entryId = $this->requireId($entryId, 'entry');
		$comment = trim($comment);
		if ($comment === '') {
			throw new \InvalidArgumentException('addComment requires a non-empty comment.');
		}

		return $this->insertRow('base3system_syscomment', [
			'parent_id' => $parentId !== null ? $this->normalizeId($parentId) : null,
			'entry_id' => $entryId,
			'comment' => $comment,
			'user_id' => $this->resolveUserId(null),
			'datetime' => $this->now()
		]) ?? 0;
	}

	public function updateComment(int|string $commentId, string $comment): void {
		$commentId = $this->requireId($commentId, 'comment');
		$comment = trim($comment);
		if ($comment === '') {
			throw new \InvalidArgumentException('updateComment requires a non-empty comment.');
		}

		$this->updateRows('base3system_syscomment', ['comment' => $comment], $this->eq('base3system_syscomment', 'id', $commentId), 1);
	}

	public function deleteComment(int|string $commentId): void {
		$commentId = $this->requireId($commentId, 'comment');
		$this->deleteRows('base3system_syscomment', $this->eq('base3system_syscomment', 'id', $commentId), 1);
	}

	private function resolveUserId(?int $userId): int {
		if ($userId !== null) {
			$id = $this->normalizeId($userId);
			if ($id !== null) return $id;
		}

		$user = $this->usermanager->getUser();
		return $user && !empty($user->id) ? (int)$user->id : 1;
	}

	private function requireId(int|string $id, string $name): int {
		$id = $this->normalizeId($id);
		if ($id === null) {
			throw new \InvalidArgumentException('Invalid ' . $name . ' id.');
		}

		return $id;
	}
}
