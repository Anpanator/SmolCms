<?php

declare(strict_types=1);

namespace SmolCms\Test\Controller;

use DateTime;
use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\AuditActionType;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Persistence\UserEntity;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;
use SmolCms\Service\DB\AuditLogService;
use SmolCms\Service\DB\UserService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;
use SmolCms\TestUtils\LoginWithAccessLevel;

class AuditLogControllerTest extends FunctionalTestCase
{
    use LoginWithAccessLevel;

    #[Autowire]
    private AuditLogService $auditLogService;

    #[Autowire]
    private UserService $userService;

    public function testAuditLogRequiresModAndFiltersByUserIdLoginNameAndDisplayName(): void
    {
        self::assertSame(HttpStatus::UNAUTHORIZED, $this->simulateAuditRequest()->getStatus());
        $this->loginUser('audit-viewer-' . bin2hex(random_bytes(4)), 'Audit Viewer');
        self::assertSame(HttpStatus::FORBIDDEN, $this->simulateAuditRequest()->getStatus());

        $token = bin2hex(random_bytes(5));
        $target = $this->createUser('audit-login-' . $token, 'Audit Display ' . $token, AccessLevel::FELLOW);
        $targetId = $target->getId();
        $this->auditLogService->record($targetId, AuditActionType::TAG_ADDED, 'image_id=42;tag=filter-' . $token);
        $this->loginUserWithAccessLevel(
            'audit-mod-' . $token,
            'Audit Mod',
            AccessLevel::MOD,
        );

        foreach ([(string)$targetId, 'audit-login-' . $token, 'Audit Display ' . $token] as $query) {
            $response = $this->simulateAuditRequest(['q' => $query]);
            self::assertSame(HttpStatus::OK, $response->getStatus());
            $body = json_decode((string)$response->getContent(), true, JSON_THROW_ON_ERROR);
            self::assertSame(1, $body['total']);
            self::assertCount(1, $body['entries']);
            self::assertSame([
                'id' => $targetId,
                'loginName' => 'audit-login-' . $token,
                'displayName' => 'Audit Display ' . $token,
            ], $body['entries'][0]['user']);
            self::assertSame(AuditActionType::TAG_ADDED->value, $body['entries'][0]['actionType']);
            self::assertSame('image_id=42;tag=filter-' . $token, $body['entries'][0]['context']);
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $body['entries'][0]['time']);
        }
    }

    public function testAuditLogPaginatesNewestIdFirstAndRejectsInvalidParameters(): void
    {
        $token = bin2hex(random_bytes(5));
        $target = $this->createUser('audit-page-' . $token, 'Audit Page ' . $token, AccessLevel::NOVICE);
        for ($index = 0; $index < 31; $index++) {
            $this->auditLogService->record(
                $target->getId(),
                $index % 2 === 0 ? AuditActionType::TAG_ADDED : AuditActionType::TAG_REMOVED,
                'row-' . $index,
            );
        }
        $this->loginUserWithAccessLevel('audit-page-mod-' . $token, 'Audit Page Mod', AccessLevel::MOD);

        $firstResponse = $this->simulateAuditRequest(['page' => '1', 'q' => $token]);
        $firstPage = json_decode((string)$firstResponse->getContent(), true, JSON_THROW_ON_ERROR);
        self::assertSame(HttpStatus::OK, $firstResponse->getStatus());
        self::assertSame(31, $firstPage['total']);
        self::assertSame(30, $firstPage['perPage']);
        self::assertCount(30, $firstPage['entries']);
        self::assertSame('row-30', $firstPage['entries'][0]['context']);
        self::assertSame('row-1', $firstPage['entries'][29]['context']);

        $secondResponse = $this->simulateAuditRequest(['page' => '2', 'q' => $token]);
        $secondPage = json_decode((string)$secondResponse->getContent(), true, JSON_THROW_ON_ERROR);
        self::assertSame(2, $secondPage['page']);
        self::assertSame(31, $secondPage['total']);
        self::assertCount(1, $secondPage['entries']);
        self::assertSame('row-0', $secondPage['entries'][0]['context']);
        self::assertSame(HttpStatus::BAD_REQUEST, $this->simulateAuditRequest(['page' => '0'])->getStatus());
        self::assertSame(HttpStatus::BAD_REQUEST, $this->simulateAuditRequest(['q' => ['not-a-string']])->getStatus());
    }

    private function createUser(string $loginName, string $displayName, AccessLevel $accessLevel): UserEntity
    {
        $user = new UserEntity(
            id: null,
            loginName: $loginName,
            password: password_hash('secure-password-123', PASSWORD_DEFAULT),
            displayName: $displayName,
            state: 'active',
            registerDate: new DateTime('2026-10-19 12:00:00'),
            lastLoginDate: null,
            accessLevel: $accessLevel,
        );
        $this->userService->saveAsNew($user);
        return $user;
    }

    private function simulateAuditRequest(?array $params = null): Response
    {
        return $this->simulateRequest(new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/api/audit-log'),
            method: HttpMethod::GET,
            getParams: $params,
        ));
    }
}
