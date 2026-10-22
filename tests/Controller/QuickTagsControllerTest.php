<?php

declare(strict_types=1);

namespace SmolCms\Test\Controller;

use PDO;
use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\Tag\TagService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;

class QuickTagsControllerTest extends FunctionalTestCase
{
    #[Autowire]
    private TagService $tagService;

    #[Autowire]
    private SessionService $sessionService;

    #[Autowire]
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo->exec('DELETE FROM user_tag_usage');
        $this->pdo->exec('DELETE FROM image_tag');
        $this->pdo->exec("DELETE FROM tag WHERE name <> 'tagme'");
        $this->pdo->exec('UPDATE tag SET usage_count = 0');
    }

    public function testQuickTagsRequiresLoginAndReturnsEmptyListsForANewUser(): void
    {
        $anonymous = $this->simulateQuickTagsGet();
        self::assertSame(HttpStatus::UNAUTHORIZED, $anonymous->getStatus());
        self::assertNull($anonymous->getContent());

        $this->loginUser('quick-tags-empty-' . bin2hex(random_bytes(4)), 'Quick Tags Empty');
        $response = $this->simulateQuickTagsGet();

        self::assertSame(HttpStatus::OK, $response->getStatus());
        self::assertSame(
            ['global' => [], 'userMostUsed' => [], 'userRecent' => []],
            json_decode((string)$response->getContent(), true, JSON_THROW_ON_ERROR),
        );
    }

    public function testQuickTagsReturnsOrderedLimitedAndDeduplicatedLists(): void
    {
        $this->loginUser('quick-tags-order-' . bin2hex(random_bytes(4)), 'Quick Tags Order');
        $user = $this->sessionService->getUserData();
        self::assertNotNull($user);

        $prefix = 'quick-' . bin2hex(random_bytes(4)) . '-';
        $tagNames = [];
        $usageInsert = $this->pdo->prepare(
            'INSERT INTO user_tag_usage (user_id, tag_id, usage_count, last_used_at) '
            . 'VALUES (:user_id, :tag_id, :usage_count, :last_used_at)'
        );
        $globalUsageUpdate = $this->pdo->prepare('UPDATE tag SET usage_count = :usage_count WHERE id = :tag_id');

        for ($index = 0; $index < 25; $index++) {
            $tagName = $prefix . sprintf('%02d', $index);
            $tagNames[] = $tagName;
            $category = $index === 0 ? 'Artist' : 'General';
            self::assertNotNull($this->tagService->createTag($tagName, $category));
            $tagId = $this->tagService->findOrCreateTagId($tagName);
            $globalUsageUpdate->execute(['usage_count' => 50 - $index, 'tag_id' => $tagId]);
            $usageInsert->execute([
                'user_id' => $user->id,
                'tag_id' => $tagId,
                'usage_count' => $index < 12 ? 12 - $index : 1,
                'last_used_at' => sprintf('2026-10-22 12:%02d:00', 30 - $index),
            ]);
        }

        $response = $this->simulateQuickTagsGet();

        self::assertSame(HttpStatus::OK, $response->getStatus());
        $body = json_decode((string)$response->getContent(), true, JSON_THROW_ON_ERROR);
        self::assertSame(['global', 'userMostUsed', 'userRecent'], array_keys($body));
        self::assertCount(20, $body['global']);
        self::assertCount(10, $body['userMostUsed']);
        self::assertCount(10, $body['userRecent']);
        self::assertSame(array_slice($tagNames, 0, 20), array_column($body['global'], 'name'));
        self::assertSame('Artist', $body['global'][0]['category']);
        self::assertSame(array_slice($tagNames, 0, 10), array_column($body['userMostUsed'], 'name'));
        self::assertSame(array_slice($tagNames, 10, 10), array_column($body['userRecent'], 'name'));
        self::assertSame([], array_values(array_intersect(
            array_column($body['userMostUsed'], 'name'),
            array_column($body['userRecent'], 'name'),
        )));
        self::assertNotContains('tagme', array_column($body['global'], 'name'));
        self::assertNotContains('tagme', array_column($body['userMostUsed'], 'name'));
        self::assertNotContains('tagme', array_column($body['userRecent'], 'name'));
    }

    private function simulateQuickTagsGet(): Response
    {
        return $this->simulateRequest(new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/api/tags/quick'),
            method: HttpMethod::GET,
        ));
    }
}
