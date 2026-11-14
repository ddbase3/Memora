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

use Base3\CoreFoundation\Authorization\Dto\AuthorizationDecision;
use Base3\CoreFoundation\Authorization\Dto\AuthorizationRequest;
use Base3\CoreFoundation\Authorization\Dto\ResourceReference;
use Base3\CoreFoundation\Usermanager\Permission;
use Base3\Memora\Service\MemoraResourceAuthorizationService;
use PHPUnit\Framework\TestCase;
use Base3\ResourceFoundation\Api\IEntityDataService;

final class MemoraResourceAuthorizationServiceTest extends TestCase {

	public function testNativeEntryGrantsAndLocksAreUsedForBothDecisionMethods(): void {
		$entries = $this->createMock(IEntityDataService::class);
		$rows = [
			10 => ['id' => 10, 'access' => 'view', 'dellock' => 0],
			11 => ['id' => 11, 'access' => 'edit', 'dellock' => 1]
		];
		$entries->method('getEntry')->willReturnCallback(static fn($id) => $rows[$id] ?? null);
		$entries->method('getEntries')->willReturnCallback(static fn(array $options): array => array_values(array_filter(
			$rows, static fn(array $row): bool => in_array($row['id'], $options['entry'], true)
		)));
		$service = new MemoraResourceAuthorizationService($entries);
		$requests = [
			'a' => $this->request(10, 'view'), 'b' => $this->request(10, 'edit'),
			'c' => $this->request(11, 'view'), 'd' => $this->request(11, 'edit'),
			'e' => $this->request(99, 'view')
		];
		$batch = $service->decideMany($requests);
		self::assertSame(array_keys($requests), array_keys($batch));
		foreach ($requests as $key => $request) {
			$single = $service->decide($request);
			self::assertSame($single->outcome, $batch[$key]->outcome);
			self::assertSame($single->reasonCode, $batch[$key]->reasonCode);
		}
		self::assertSame(AuthorizationDecision::ALLOW, $batch['a']->outcome);
		self::assertSame(AuthorizationDecision::DENY, $batch['b']->outcome);
		self::assertSame('entry_edit_locked', $batch['d']->reasonCode);
		self::assertSame('entry_not_visible', $batch['e']->reasonCode);
	}

	public function testUntrustedPrincipalIsNeverEvaluatedAsCurrentUser(): void {
		$entries = $this->createMock(IEntityDataService::class);
		$entries->expects(self::never())->method('getEntry');
		$service = new MemoraResourceAuthorizationService($entries);
		$request = new AuthorizationRequest('xrm', Permission::for('entry', 'edit'),
			new ResourceReference('xrm', 'entry', 10), new \Base3\CoreFoundation\Authorization\Dto\SubjectReference('xrm', 'user', 27));
		self::assertSame(AuthorizationDecision::DENY, $service->decide($request)->outcome);
	}

	private function request(int $id, string $permission): AuthorizationRequest {
		return new AuthorizationRequest('xrm', Permission::for('entry', $permission), new ResourceReference('xrm', 'entry', $id));
	}
}
