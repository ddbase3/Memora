<?php declare(strict_types=1);

namespace Memora\Service;

use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationDecision;
use Base3\Authorization\Dto\AuthorizationRequest;
use ResourceFoundation\Api\IEntityDataService;
use InvalidArgumentException;

/** XRM entry decisions delegate to the authoritative Memora query/access pipeline. */
final class MemoraResourceAuthorizationService implements IAuthorizationService {

	public function __construct(private readonly IEntityDataService $entries) {}

	public function getDefaultAuthority(): string { return 'xrm'; }

	public function decide(AuthorizationRequest $request): AuthorizationDecision {
		if ($request->authority !== 'xrm') return AuthorizationDecision::indeterminate('unknown_authority');
		if ($request->subject !== null || $request->onBehalfOf !== null) return AuthorizationDecision::deny('unsupported_principal');
		$resource = $request->resource;
		if ($resource === null || $resource->type !== 'entry' || !ctype_digit((string)$resource->id) || (int)$resource->id <= 0) {
			return AuthorizationDecision::deny('invalid_entry_resource');
		}
		if ($request->permission->scope !== 'entry' || !in_array($request->permission->permission, ['view', 'edit'], true)) {
			return AuthorizationDecision::deny('unsupported_entry_permission');
		}
		try {
			$entry = $this->entries->getEntry($resource->id, ['loadaccess' => true]);
		} catch (\Throwable) {
			return AuthorizationDecision::indeterminate('entry_lookup_failed');
		}
		if ($entry === null) return AuthorizationDecision::deny('entry_not_visible');
		$mode = (string)($entry['access'] ?? 'none');
		if ($request->permission->permission === 'edit' && !empty($entry['dellock'])) return AuthorizationDecision::deny('entry_edit_locked');
		if ($mode === 'edit' || ($mode === 'view' && $request->permission->permission === 'view')) {
			return AuthorizationDecision::allow('memora_entry_access');
		}
		return AuthorizationDecision::deny('entry_access_insufficient');
	}

	public function decideMany(array $requests): array {
		$decisions = [];
		$batched = [];
		foreach ($requests as $key => $request) {
			if (!$request instanceof AuthorizationRequest) throw new InvalidArgumentException('Expected AuthorizationRequest.');
			$id = $request->resource?->id;
			if ($request->authority !== 'xrm' || $request->subject !== null || $request->onBehalfOf !== null ||
				$request->resource?->type !== 'entry' || $id === null || !ctype_digit((string)$id) || (int)$id <= 0 ||
				$request->permission->scope !== 'entry' || !in_array($request->permission->permission, ['view', 'edit'], true)) {
				$decisions[$key] = $this->decide($request);
				continue;
			}
			$batched[$key] = $request;
		}
		$ids = array_values(array_unique(array_map(static fn(AuthorizationRequest $request): int => (int)$request->resource->id, $batched)));
		$modes = [];
		try {
			// Memora's FilterByEntryExtension and LoadAccessExtension enforce the same
			// native ACL in one query rather than issuing N independent lookups.
			foreach (array_chunk($ids, 300) as $chunk) {
				foreach ($this->entries->getEntries(['entry' => $chunk, 'loadaccess' => true]) as $row) {
					$id = (int)($row['id'] ?? 0);
					if ($id > 0 && !isset($modes[$id])) $modes[$id] = [
						'mode' => (string)($row['access'] ?? 'none'),
						'locked' => !empty($row['dellock'])
					];
				}
			}
			foreach ($batched as $key => $request) {
				$entryId = (int)$request->resource->id;
				if (!array_key_exists($entryId, $modes)) {
					$decisions[$key] = AuthorizationDecision::deny('entry_not_visible');
					continue;
				}
				$mode = $modes[$entryId]['mode'];
				if ($request->permission->permission === 'edit' && $modes[$entryId]['locked']) {
					$decisions[$key] = AuthorizationDecision::deny('entry_edit_locked');
					continue;
				}
				$decisions[$key] = $mode === 'edit' || ($mode === 'view' && $request->permission->permission === 'view')
					? AuthorizationDecision::allow('memora_entry_access')
					: AuthorizationDecision::deny('entry_access_insufficient');
			}
		} catch (\Throwable) {
			foreach ($batched as $key => $request) $decisions[$key] = AuthorizationDecision::indeterminate('entry_lookup_failed');
		}
		$ordered = [];
		foreach ($requests as $key => $request) $ordered[$key] = $decisions[$key];
		return $ordered;
	}
}
