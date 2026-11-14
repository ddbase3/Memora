<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of Memora for BASE3 Framework.
 *
 * Memora is a modular XRM/CRM backend and knowledge graph for BASE3.
 * It provides structured entities, relations, metadata and tags.
 * ResourceFoundation contracts govern its shared entity services.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de
 * https://github.com/ddbase3/Memora
 **********************************************************************/

namespace Base3\Memora\Extension;

use Base3\CoreFoundation\Api\ISortable;
use Base3\CoreFoundation\Usermanager\Api\IUsermanager;
use Base3\CoreFoundation\Usermanager\Permission;
use Base3\CoreFoundation\Authorization\Api\IAuthorizationService;
use Base3\CoreFoundation\Authorization\Dto\AuthorizationRequest;
use Base3\Memora\Api\IMemoraRoleResolver;
use Base3\Memora\Api\IMemoraQueryExtension;
use Base3\Memora\Dto\VerifiedResourceQuery;

class AccessExtension implements IMemoraQueryExtension, ISortable {

	/** Default public (unauthenticated) user ID */
	private const DEFAULT_USER_ID = 1;

	/** Default group ID for all authenticated members */
	private const DEFAULT_GROUP_ID = 1;

	public function __construct(
		private readonly IUsermanager $usermanager,
		private readonly IMemoraRoleResolver $roles,
		private readonly IAuthorizationService $authorization
	) {}

	public function isApplicable(array $options): bool {
		return !$this->isVerifiedTechnicalQuery($options) && !$this->hasEntryAdminPermission();
	}

	public function applyToQuery(array $query, array $options): array {
		$user = $this->usermanager->getUser();

		$conditions = [
			$this->buildPublicUserAccessCondition()
		];

		if ($user && !empty($user->id)) {
			$conditions[] = [ 'type' => 'op', 'operator' => '=', 'params' => [
				[ 'type' => 'fld', 'table' => 'base3system_sysuseraccess', 'field' => 'user_id', 'variant' => 'required' ],
				$user->id
			]];
		}

		$groupIds = $this->roles->getCurrentGroupIds();
		if (!empty($groupIds)) {
			$conditions[] = [ 'type' => 'op', 'operator' => 'IN', 'params' => [
				[ 'type' => 'fld', 'table' => 'base3system_sysgroupaccess', 'field' => 'group_id', 'variant' => 'optional' ],
				$groupIds
			]];
		}

		$query['where'][] = [
			'type' => 'op',
			'operator' => 'OR',
			'params' => $conditions
		];

		return $query;
	}

	public function processResult(array $rows, array $options): array {
		return $rows;
	}

	private function isVerifiedTechnicalQuery(array $options): bool {
        $marker = $options['__memora_verified_resource_query'] ?? null;
        return $marker instanceof VerifiedResourceQuery &&
            is_array($options['filter'] ?? null) && $marker->matches($options['filter']);
    }

	private function buildPublicUserAccessCondition(): array {
		return [
			'type' => 'op',
			'operator' => 'AND',
			'params' => [
				[ 'type' => 'op', 'operator' => '=', 'params' => [
					[ 'type' => 'fld', 'table' => 'base3system_sysuseraccess', 'field' => 'user_id', 'variant' => 'required' ],
					self::DEFAULT_USER_ID
				]],
				[ 'type' => 'op', 'operator' => '!=', 'params' => [
					[ 'type' => 'fld', 'table' => 'base3system_sysuseraccess', 'field' => 'mode', 'variant' => 'required' ],
					'owner'
				]]
			]
		];
	}

	private function hasEntryAdminPermission(): bool {
		return $this->authorization->decide(new AuthorizationRequest('base3', Permission::for('entry', 'admin')))->isAllowed();
	}

	public function getPriority(): int {
		return 780;
	}
}
