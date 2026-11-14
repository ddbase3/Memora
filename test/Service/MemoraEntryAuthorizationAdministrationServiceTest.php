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

namespace Base3\Memora\Test\Service;

use Base3\CoreFoundation\Authorization\Dto\ResourceReference;
use Base3\CoreFoundation\Authorization\Dto\SubjectReference;
use Base3\CoreFoundation\Usermanager\Permission;
use Base3\Memora\Api\IMemoraQueryService;
use Base3\Memora\Service\MemoraEntryAuthorizationAdministrationService;
use PHPUnit\Framework\TestCase;
use Base3\ResourceFoundation\Api\IEntityDataService;
use Base3\ResourceFoundation\Dto\QueryResult;

final class MemoraEntryAuthorizationAdministrationServiceTest extends TestCase {

	public function testGrantMutationDelegatesToExistingEntityUpdatePipeline(): void {
		$query = $this->createMock(IMemoraQueryService::class);
		$query->method('executeQuery')->willReturnCallback(static function(array $request): QueryResult {
			$table = $request['table'] ?? '';
			$rows = match ($table) {
				'base3system_sysuseraccess' => [['user_id' => 15, 'mode' => 'owner'], ['user_id' => 16, 'mode' => 'visitor']],
				'base3system_sysuser' => [['id' => 16]],
				default => []
			};
			return new QueryResult([], $rows);
		});
		$entries = $this->createMock(IEntityDataService::class);
		$entries->method('getEntry')->willReturn(['id' => 22, 'access' => 'edit', 'dellock' => 0]);
		$entries->expects(self::once())->method('updateEntry')->with(22, [
			'replaceuseraccess' => [['user_id' => 15, 'mode' => 'owner'], ['user_id' => 16, 'mode' => 'moderator']]
		]);
		$service = new MemoraEntryAuthorizationAdministrationService($query, $entries);
		$service->replaceGrants(new ResourceReference('xrm', 'entry', 22),
			new SubjectReference('xrm', 'user', 16), [Permission::for('entry', 'moderator')]);
	}

	public function testProtectedLastOwnerCannotBeRemoved(): void {
		$query = $this->createMock(IMemoraQueryService::class);
		$query->method('executeQuery')->willReturnCallback(static fn(array $request): QueryResult => new QueryResult([], match ($request['table'] ?? '') {
			'base3system_sysuseraccess' => [['user_id' => 15, 'mode' => 'owner']],
			'base3system_sysuser' => [['id' => 15]],
			default => []
		}));
		$entries = $this->createMock(IEntityDataService::class);
		$entries->method('getEntry')->willReturn(['id' => 22, 'access' => 'edit']);
		$entries->expects(self::never())->method('updateEntry');
		$this->expectException(\RuntimeException::class);
		(new MemoraEntryAuthorizationAdministrationService($query, $entries))
			->replaceGrants(new ResourceReference('xrm', 'entry', 22), new SubjectReference('xrm', 'user', 15), []);
	}
}
