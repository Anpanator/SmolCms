<?php

declare(strict_types=1);

namespace SmolCms\Test\Controller;

use DateTime;
use PDO;
use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\AuditActionType;
use SmolCms\Data\Constant\HttpContentType;
use SmolCms\Data\Constant\HttpHeader;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Constant\ImageStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;
use SmolCms\Service\DB\AuditLogService;
use SmolCms\Service\DB\ImageEntityService;
use SmolCms\Service\Tag\TagService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;
use SmolCms\TestUtils\ImageTestUtils;
use SmolCms\TestUtils\LoginWithAccessLevel;

class TagApiControllerTest extends FunctionalTestCase
{
    use LoginWithAccessLevel;

    #[Autowire]
    private TagService $tagService;

    #[Autowire]
    private ImageEntityService $imageEntityService;

    #[Autowire]
    private AuditLogService $auditLogService;

    #[Autowire]
    private PDO $pdo;

    public function testModerationEndpointsRequireModAccess(): void
    {
        $routes = [
            [HttpMethod::GET, '/api/tags', ['q' => 'tag']],
            [HttpMethod::POST, '/api/tags', ['name' => 'unauthorized-tag']],
            [HttpMethod::GET, '/api/tag-categories', null],
            [HttpMethod::POST, '/api/tag-categories', ['name' => 'unauthorized-category']],
        ];

        foreach ($routes as [$method, $path, $params]) {
            $response = $this->simulateApiRequest($method, $path, $params);
            self::assertSame(HttpStatus::UNAUTHORIZED, $response->getStatus());
            self::assertNull($response->getContent());
        }

        $this->loginUser('tag-api-novice', 'Tag API Novice');
        foreach ($routes as [$method, $path, $params]) {
            $response = $this->simulateApiRequest($method, $path, $params);
            self::assertSame(HttpStatus::FORBIDDEN, $response->getStatus());
            self::assertNull($response->getContent());
        }
    }

    public function testSuggestionsArePublicAndEmptyQueriesReturnEmptyList(): void
    {
        foreach ([null, ['q' => '']] as $params) {
            $response = $this->simulateApiRequest(HttpMethod::GET, '/api/tags/suggest', $params);
            self::assertSame(HttpStatus::OK, $response->getStatus());
            self::assertSame(
                ['tags' => []],
                json_decode((string)$response->getContent(), true, JSON_THROW_ON_ERROR)
            );
        }

        $invalidQuery = $this->simulateApiRequest(HttpMethod::GET, '/api/tags/suggest', [
            'q' => ['not', 'text'],
        ]);
        self::assertSame(HttpStatus::BAD_REQUEST, $invalidQuery->getStatus());
    }

    public function testSuggestionsRemainPublicForLoggedInUserAndReturnExpectedShape(): void
    {
        $prefix = 'logged-in-suggest-' . bin2hex(random_bytes(5));
        $tagName = $prefix . '-target';
        $this->tagService->createTag($tagName);
        $this->loginUser('tag-api-suggest-user-' . bin2hex(random_bytes(4)), 'Tag Suggest User');

        $response = $this->simulateApiRequest(HttpMethod::GET, '/api/tags/suggest', ['q' => $prefix]);

        self::assertSame(HttpStatus::OK, $response->getStatus());
        self::assertSame(
            ['tags' => [$tagName]],
            json_decode((string)$response->getContent(), true, JSON_THROW_ON_ERROR),
        );
    }

    public function testSuggestionsUsePrefixMatchUsageOrderingAndFifteenItemLimit(): void
    {
        $prefix = 'api-suggest-' . bin2hex(random_bytes(4)) . '-';
        $tagNames = [];
        for ($index = 0; $index < 16; $index++) {
            $tagNames[] = $prefix . sprintf('%02d', $index);
            $this->tagService->createTag($tagNames[$index]);
        }
        $firstImage = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
            uploadDate: new DateTime('2026-10-03 10:00:00'),
        );
        $secondImage = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
            uploadDate: new DateTime('2026-10-03 11:00:00'),
        );
        $this->tagService->attachTagToImage($firstImage->getId(), $tagNames[14]);
        $this->tagService->attachTagToImage($secondImage->getId(), $tagNames[14]);
        $this->tagService->attachTagToImage($firstImage->getId(), $tagNames[13]);

        $response = $this->simulateApiRequest(HttpMethod::GET, '/api/tags/suggest', [
            'q' => mb_strtoupper($prefix, 'UTF-8'),
        ]);

        self::assertSame(HttpStatus::OK, $response->getStatus());
        $body = json_decode((string)$response->getContent(), true, JSON_THROW_ON_ERROR);
        self::assertCount(15, $body['tags']);
        self::assertSame($tagNames[14], $body['tags'][0]);
        self::assertSame($tagNames[13], $body['tags'][1]);
        self::assertSame($tagNames[0], $body['tags'][2]);
        self::assertNotContains($tagNames[15], $body['tags']);
    }

    public function testCreateTagNormalizesNamesSupportsCategoryAndRejectsDuplicates(): void
    {
        $this->loginUserWithAccessLevel('tag-api-mod-create', 'Tag API Mod', AccessLevel::MOD);

        $response = $this->simulateApiRequest(HttpMethod::POST, '/api/tags', [
            'name' => "  Tag   with\tSpaces  ",
            'category' => 'aRtIsT',
        ]);

        self::assertSame(HttpStatus::CREATED, $response->getStatus());
        self::assertSame(HttpContentType::JSON->value, $response->getHeaders()[HttpHeader::CONTENT_TYPE->value]);
        self::assertSame(
            ['tag' => ['name' => 'tag with spaces', 'category' => 'Artist', 'usageCount' => 0]],
            json_decode((string)$response->getContent(), true, JSON_THROW_ON_ERROR)
        );

        $duplicateResponse = $this->simulateApiRequest(HttpMethod::POST, '/api/tags', [
            'name' => 'tag WITH spaces',
            'category' => 'General',
        ]);
        self::assertSame(HttpStatus::CONFLICT, $duplicateResponse->getStatus());
        self::assertNull($duplicateResponse->getContent());

        $defaultCategoryResponse = $this->simulateApiRequest(HttpMethod::POST, '/api/tags', [
            'name' => 'tag-api-default-category',
        ]);
        self::assertSame(HttpStatus::CREATED, $defaultCategoryResponse->getStatus());
        self::assertSame(
            ['tag' => ['name' => 'tag-api-default-category', 'category' => 'General', 'usageCount' => 0]],
            json_decode((string)$defaultCategoryResponse->getContent(), true, JSON_THROW_ON_ERROR)
        );
    }

    public function testCreateTagRejectsUnicodeCaseVariantsAsDuplicates(): void
    {
        $this->loginUserWithAccessLevel('tag-api-mod-unicode', 'Tag API Mod', AccessLevel::MOD);
        $suffix = bin2hex(random_bytes(4));
        $tagName = 'Ätag-' . $suffix;

        $createdResponse = $this->simulateApiRequest(HttpMethod::POST, '/api/tags', ['name' => $tagName]);
        self::assertSame(HttpStatus::CREATED, $createdResponse->getStatus());
        self::assertSame(
            ['tag' => ['name' => mb_strtolower($tagName, 'UTF-8'), 'category' => 'General', 'usageCount' => 0]],
            json_decode((string)$createdResponse->getContent(), true, JSON_THROW_ON_ERROR),
        );

        foreach (['ätag-' . $suffix, 'ÄTAG-' . $suffix] as $duplicateName) {
            $duplicateResponse = $this->simulateApiRequest(HttpMethod::POST, '/api/tags', ['name' => $duplicateName]);
            self::assertSame(HttpStatus::CONFLICT, $duplicateResponse->getStatus());
            self::assertNull($duplicateResponse->getContent());
        }

        $countStatement = $this->pdo->prepare('SELECT COUNT(*) FROM tag WHERE name = :name');
        $countStatement->execute(['name' => mb_strtolower($tagName, 'UTF-8')]);
        self::assertSame(1, (int)$countStatement->fetchColumn());
    }

    public function testCreateTagInvalidNamesAndCategoriesReturnBadRequest(): void
    {
        $this->loginUserWithAccessLevel('tag-api-mod-invalid', 'Tag API Mod', AccessLevel::MOD);

        $invalidBodies = [
            ['name' => ''],
            ['name' => '   '],
            ['name' => 'contains,comma'],
            ['name' => str_repeat('x', 101)],
            ['name' => 'unknown-category-tag', 'category' => 'Missing category'],
            [],
        ];
        foreach ($invalidBodies as $body) {
            $response = $this->simulateApiRequest(HttpMethod::POST, '/api/tags', $body);
            self::assertSame(HttpStatus::BAD_REQUEST, $response->getStatus());
            self::assertNull($response->getContent());
        }
    }

    public function testBrowseTagsReturnsExactPageShapeAndPartialSearch(): void
    {
        $this->loginUserWithAccessLevel('tag-api-mod-browse', 'Tag API Mod', AccessLevel::MOD);
        $prefix = 'api-browse-' . bin2hex(random_bytes(4)) . '-';
        for ($index = 0; $index < 26; $index++) {
            $this->tagService->createTag($prefix . sprintf('%03d', $index));
        }

        $response = $this->simulateApiRequest(HttpMethod::GET, '/api/tags', [
            'page' => '2',
            'q' => mb_strtoupper(substr($prefix, 1, -1), 'UTF-8'),
        ]);

        self::assertSame(HttpStatus::OK, $response->getStatus());
        $body = json_decode((string)$response->getContent(), true, JSON_THROW_ON_ERROR);
        self::assertSame(['tags', 'page', 'perPage', 'total'], array_keys($body));
        self::assertSame(2, $body['page']);
        self::assertSame(25, $body['perPage']);
        self::assertSame(26, $body['total']);
        self::assertCount(1, $body['tags']);
        self::assertSame(
            ['name' => $prefix . '025', 'category' => 'General', 'usageCount' => 0],
            $body['tags'][0]
        );
    }

    public function testBrowseTagsRejectsInvalidPageAndQueryTypes(): void
    {
        $this->loginUserWithAccessLevel('tag-api-mod-page', 'Tag API Mod', AccessLevel::MOD);

        foreach ([['page' => '0'], ['page' => '-1'], ['page' => '1.5'], ['page' => 'abc'], ['q' => ['not', 'text']]] as $params) {
            $response = $this->simulateApiRequest(HttpMethod::GET, '/api/tags', $params);
            self::assertSame(HttpStatus::BAD_REQUEST, $response->getStatus());
            self::assertNull($response->getContent());
        }
    }

    public function testTagCategoriesAreSortedAndCanBeCreatedWithDuplicateProtection(): void
    {
        $this->loginUserWithAccessLevel('tag-api-mod-categories', 'Tag API Mod', AccessLevel::MOD);
        $categoryName = 'Zzz Category ' . bin2hex(random_bytes(4));

        $createResponse = $this->simulateApiRequest(HttpMethod::POST, '/api/tag-categories', [
            'name' => "  $categoryName  ",
        ]);
        self::assertSame(HttpStatus::CREATED, $createResponse->getStatus());
        self::assertSame(
            ['category' => $categoryName],
            json_decode((string)$createResponse->getContent(), true, JSON_THROW_ON_ERROR)
        );

        $duplicateResponse = $this->simulateApiRequest(HttpMethod::POST, '/api/tag-categories', [
            'name' => mb_strtoupper($categoryName, 'UTF-8'),
        ]);
        self::assertSame(HttpStatus::CONFLICT, $duplicateResponse->getStatus());
        self::assertNull($duplicateResponse->getContent());

        foreach ([['name' => ''], ['name' => 'has,comma'], ['name' => str_repeat('x', 101)], []] as $body) {
            $invalidResponse = $this->simulateApiRequest(HttpMethod::POST, '/api/tag-categories', $body);
            self::assertSame(HttpStatus::BAD_REQUEST, $invalidResponse->getStatus());
            self::assertNull($invalidResponse->getContent());
        }

        $listResponse = $this->simulateApiRequest(HttpMethod::GET, '/api/tag-categories');
        self::assertSame(HttpStatus::OK, $listResponse->getStatus());
        $categories = json_decode((string)$listResponse->getContent(), true, JSON_THROW_ON_ERROR)['categories'];
        self::assertContains('Artist', $categories);
        self::assertContains('Copyright', $categories);
        self::assertContains('General', $categories);
        self::assertContains($categoryName, $categories);
        $sortedCategories = $categories;
        usort($sortedCategories, static fn(string $left, string $right): int => strcasecmp($left, $right));
        self::assertSame($sortedCategories, $categories);
    }

    public function testDeleteTagRequiresAdminAccess(): void
    {
        $this->tagService->createTag('delete-guard-tag');

        $anonymous = $this->simulateApiRequest(HttpMethod::POST, '/api/tags/delete', ['name' => 'delete-guard-tag']);
        self::assertSame(HttpStatus::UNAUTHORIZED, $anonymous->getStatus());
        self::assertNull($anonymous->getContent());

        $this->loginUser('tag-delete-novice', 'Tag Delete Novice');
        $novice = $this->simulateApiRequest(HttpMethod::POST, '/api/tags/delete', ['name' => 'delete-guard-tag']);
        self::assertSame(HttpStatus::FORBIDDEN, $novice->getStatus());
        self::assertNull($novice->getContent());

        $this->loginUserWithAccessLevel('tag-delete-mod', 'Tag Delete Mod', AccessLevel::MOD);
        $moderator = $this->simulateApiRequest(HttpMethod::POST, '/api/tags/delete', ['name' => 'delete-guard-tag']);
        self::assertSame(HttpStatus::FORBIDDEN, $moderator->getStatus());
        self::assertNull($moderator->getContent());

        self::assertNotNull($this->tagService->getUsageCount('delete-guard-tag'));
    }

    public function testAdminDeletesTagUnassignsItFromImagesAndWritesAuditEntry(): void
    {
        $loginName = 'tag-delete-admin-' . bin2hex(random_bytes(4));
        $this->loginUserWithAccessLevel($loginName, 'Tag Delete Admin', AccessLevel::ADMIN);
        $tagName = 'deletable-' . bin2hex(random_bytes(4));
        $this->tagService->createTag($tagName);
        $image = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
            uploadDate: new DateTime('2026-10-03 10:00:00'),
        );
        $this->tagService->attachTagToImage($image->getId(), $tagName);
        self::assertSame(1, $this->tagService->getUsageCount($tagName));

        $response = $this->simulateApiRequest(HttpMethod::POST, '/api/tags/delete', [
            'name' => '  ' . mb_strtoupper($tagName, 'UTF-8') . '  ',
        ]);

        self::assertSame(HttpStatus::NO_CONTENT, $response->getStatus());
        self::assertNull($response->getContent());
        self::assertNull($this->tagService->getUsageCount($tagName));

        $associationStatement = $this->pdo->prepare('SELECT COUNT(*) FROM image_tag WHERE image_id = :image_id');
        $associationStatement->execute(['image_id' => $image->getId()]);
        self::assertSame(0, (int)$associationStatement->fetchColumn());

        $entries = $this->auditLogService->getEntries(1, $loginName)['entries'];
        self::assertCount(1, $entries);
        self::assertSame(AuditActionType::TAG_DELETED->value, $entries[0]['actionType']);
        self::assertSame("tag=$tagName", $entries[0]['context']);
    }

    public function testDeleteTagProtectedUnknownAndInvalidNamesReturnExpectedStatuses(): void
    {
        $this->loginUserWithAccessLevel('tag-delete-admin-errors', 'Tag Delete Admin Errors', AccessLevel::ADMIN);

        $protected = $this->simulateApiRequest(HttpMethod::POST, '/api/tags/delete', ['name' => 'TaGmE']);
        self::assertSame(HttpStatus::CONFLICT, $protected->getStatus());
        self::assertNull($protected->getContent());

        $unknown = $this->simulateApiRequest(
            HttpMethod::POST,
            '/api/tags/delete',
            ['name' => 'never-created-' . bin2hex(random_bytes(4))],
        );
        self::assertSame(HttpStatus::NOT_FOUND, $unknown->getStatus());
        self::assertNull($unknown->getContent());

        foreach ([[], ['name' => ''], ['name' => str_repeat('x', 101)]] as $body) {
            $invalid = $this->simulateApiRequest(HttpMethod::POST, '/api/tags/delete', $body);
            self::assertSame(HttpStatus::BAD_REQUEST, $invalid->getStatus());
            self::assertNull($invalid->getContent());
        }
    }

    private function simulateApiRequest(HttpMethod $method, string $path, ?array $params = null): Response
    {
        return $this->simulateRequest(new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: $path),
            method: $method,
            postParams: $method === HttpMethod::POST ? $params : null,
            getParams: $method === HttpMethod::GET ? $params : null,
        ));
    }
}
