<?php declare(strict_types=1);

namespace Memora\Service;

use Base3\Authorization\Api\IAuthorizationAdministrationService;
use Base3\Authorization\Dto\AuthorizationCapabilities;
use Base3\Authorization\Dto\AuthorizationGrant;
use Base3\Authorization\Dto\AuthorizationSubject;
use Base3\Authorization\Dto\ResourceReference;
use Base3\Authorization\Dto\SubjectReference;
use Base3\Usermanager\Permission;
use Memora\Api\IMemoraQueryService;
use ResourceFoundation\Api\IEntityDataService;
use InvalidArgumentException;
use RuntimeException;

/** One native Memora owner for direct user/group entry ACL grants. */
final class MemoraEntryAuthorizationAdministrationService extends AbstractMemoraTableService implements IAuthorizationAdministrationService {

	private const MODES = ['visitor', 'moderator', 'owner'];

	public function __construct(IMemoraQueryService $query, private readonly IEntityDataService $entries) {
		parent::__construct($query);
	}

	public function describe(string $authority, ?ResourceReference $resource = null): AuthorizationCapabilities {
		if ($authority !== 'xrm' || ($resource !== null && $resource->authority !== $authority)) {
			throw new InvalidArgumentException('Unsupported entry authority.');
		}
		$editable = $resource !== null && $this->canEdit($resource);
		return new AuthorizationCapabilities(
			'xrm', $editable, $editable, false, ['user', 'group'],
			['Direct user/group ACL only; no inherited grants. Global roles and permissions use the native role catalog.']
		);
	}

	public function getPermissions(string $authority, ?ResourceReference $resource = null): array {
		if ($resource === null || $authority !== 'xrm') throw new InvalidArgumentException('XRM entry resource is required.');
		$this->assertEditable($resource);
		return array_map(static fn(string $name): Permission => Permission::for('entry', $name), self::MODES);
	}

	public function getSubjects(ResourceReference $resource): array {
		$subjects = [];
		foreach ($this->getGrants($resource) as $grant) {
			$key = $grant->subject->kind . ':' . $grant->subject->id;
			$subjects[$key] = new AuthorizationSubject($grant->subject, ucfirst($grant->subject->kind) . ' ' . $grant->subject->id, true);
		}
		return array_values($subjects);
	}

	public function getGrants(ResourceReference $resource): array {
		$entryId = $this->assertEditable($resource);
		$grants = [];
		foreach (['user' => 'base3system_sysuseraccess', 'group' => 'base3system_sysgroupaccess'] as $kind => $table) {
			$field = $kind . '_id';
			$rows = $this->fetchRows($table, [$field, 'mode'], $this->eq($table, 'entry_id', $entryId));
			foreach ($rows as $row) {
				if (!in_array($row['mode'] ?? null, self::MODES, true)) continue;
				$subjectId = (int)($row[$field] ?? 0);
				if ($subjectId < 1) continue;
				$grants[] = new AuthorizationGrant(
					new SubjectReference('xrm', $kind, $subjectId),
					Permission::for('entry', $row['mode']), $resource, true, false
				);
			}
		}
		return $grants;
	}

	public function replaceGrants(ResourceReference $resource, SubjectReference $subject, array $permissions): void {
		$entryId = $this->assertEditable($resource);
		if ($subject->authority !== 'xrm' || !in_array($subject->kind, ['user', 'group'], true) ||
			!ctype_digit((string)$subject->id) || (int)$subject->id < 1 || count($permissions) > 1) {
			throw new InvalidArgumentException('Invalid XRM ACL subject or grant count.');
		}
		$mode = null;
		if ($permissions !== []) {
			$permission = $permissions[0];
			if (!$permission instanceof Permission || $permission->scope !== 'entry' ||
				!in_array($permission->permission, self::MODES, true)) {
				throw new InvalidArgumentException('Only one native visitor/moderator/owner grant is supported.');
			}
			$mode = $permission->permission;
			if ($subject->kind === 'group' && $mode === 'owner') {
				throw new InvalidArgumentException('Groups cannot hold the native owner mode.');
			}
		}
		$subjectId = (int)$subject->id;
		$table = $subject->kind === 'user' ? 'base3system_sysuseraccess' : 'base3system_sysgroupaccess';
		$field = $subject->kind . '_id';
		if (!$this->fetchRow('base3system_sys' . $subject->kind, ['id'], $this->eq('base3system_sys' . $subject->kind, 'id', $subjectId))) {
			throw new InvalidArgumentException('Unknown native ACL subject.');
		}
		// Inspect the native rows, but submit the complete replacement through the
		// existing Memora update extension. This is the only owner of ACL writes.
		$rows = $this->fetchRows($table, [$field, 'mode'], $this->eq($table, 'entry_id', $entryId));
		$replacement = [];
		$ownerCount = 0;
		$ownerIsChanged = false;
		foreach ($rows as $row) {
			$id = (int)($row[$field] ?? 0);
			if ($id < 1 || !in_array($row['mode'] ?? null, self::MODES, true)) continue;
			if ($subject->kind === 'user' && $row['mode'] === 'owner') {
				$ownerCount++;
				if ($id === $subjectId && $mode !== 'owner') $ownerIsChanged = true;
			}
			if ($id !== $subjectId) $replacement[] = [$field => $id, 'mode' => $row['mode']];
		}
		if ($ownerIsChanged && $ownerCount === 1) throw new RuntimeException('The last entry owner cannot be removed.');
		if ($mode !== null) $replacement[] = [$field => $subjectId, 'mode' => $mode];
		$this->entries->updateEntry($entryId, [
			$subject->kind === 'user' ? 'replaceuseraccess' : 'replacegroupaccess' => $replacement
		]);
	}

	private function canEdit(ResourceReference $resource): bool {
		if ($resource->authority !== 'xrm' || $resource->type !== 'entry' ||
			!ctype_digit((string)$resource->id) || (int)$resource->id <= 0) return false;
		try {
			$entry = $this->entries->getEntry($resource->id, ['loadaccess' => true]);
		} catch (\Throwable) { return false; }
		return $entry !== null && ($entry['access'] ?? 'none') === 'edit' && empty($entry['dellock']);
	}

	private function assertEditable(ResourceReference $resource): int {
		if (!$this->canEdit($resource)) throw new RuntimeException('Entry grants are not readable/editable by this actor.');
		return (int)$resource->id;
	}
}
