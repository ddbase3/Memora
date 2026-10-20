<?php declare(strict_types=1);

namespace Memora\Test\Service;

use Base3\Authorization\Dto\AuthorizationDecision;
use Base3\Authorization\Dto\AuthorizationRequest;
use Base3\Authorization\Dto\ResourceReference;
use Base3\Usermanager\Permission;
use Memora\Service\MemoraResourceAuthorizationService;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IEntityDataService;

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
			new ResourceReference('xrm', 'entry', 10), new \Base3\Authorization\Dto\SubjectReference('xrm', 'user', 27));
		self::assertSame(AuthorizationDecision::DENY, $service->decide($request)->outcome);
	}

	private function request(int $id, string $permission): AuthorizationRequest {
		return new AuthorizationRequest('xrm', Permission::for('entry', $permission), new ResourceReference('xrm', 'entry', $id));
	}
}
