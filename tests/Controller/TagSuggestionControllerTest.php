<?php

declare(strict_types=1);

namespace SmolCms\Test\Controller;

use DateTime;
use PDO;
use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Constant\ImageStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\DB\ImageEntityService;
use SmolCms\Service\DB\TagApprovalService;
use SmolCms\Service\Tag\TagService;
use SmolCms\Service\Tag\TagSuggestionService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;
use SmolCms\TestUtils\ImageTestUtils;
use SmolCms\TestUtils\LoginWithAccessLevel;

class TagSuggestionControllerTest extends FunctionalTestCase
{
    use LoginWithAccessLevel;

    #[Autowire]
    private ImageEntityService $imageEntityService;

    #[Autowire]
    private TagService $tagService;

    #[Autowire]
    private TagSuggestionService $tagSuggestionService;

    #[Autowire]
    private TagApprovalService $tagApprovalService;

    #[Autowire]
    private SessionService $sessionService;

    #[Autowire]
    private PDO $pdo;

    public function testCreateSuggestionsRequiresFellowNormalizesAndSkipsDuplicatesAndRealTags(): void
    {
        self::assertSame(
            HttpStatus::UNAUTHORIZED,
            $this->simulateSuggestionPost('/api/tag-suggestions', ['imageId' => 1, 'tags' => 'tag'])->getStatus(),
        );
        $this->loginUser('suggest-novice-' . bin2hex(random_bytes(4)), 'Suggest Novice');
        self::assertSame(
            HttpStatus::FORBIDDEN,
            $this->simulateSuggestionPost('/api/tag-suggestions', ['imageId' => 1, 'tags' => 'tag'])->getStatus(),
        );

        $this->loginUserWithAccessLevel(
            'suggest-fellow-' . bin2hex(random_bytes(4)),
            'Suggest Fellow',
            AccessLevel::FELLOW,
        );
        $userId = $this->sessionService->getUserData()->id;
        $image = ImageTestUtils::createImageEntity($this->imageEntityService, $userId);
        $firstName = 'suggest-first-' . bin2hex(random_bytes(4));
        $secondName = 'suggest-second-' . bin2hex(random_bytes(4));
        $realName = 'already-real-' . bin2hex(random_bytes(4));
        $this->tagService->createTag($realName);

        $response = $this->simulateSuggestionPost('/api/tag-suggestions', [
            'imageId' => $image->getId(),
            'tags' => mb_strtoupper($firstName, 'UTF-8') . ', ' . $realName . ', ' . $firstName . ', ' . $secondName,
        ]);
        self::assertSame(HttpStatus::CREATED, $response->getStatus());
        self::assertSame(
            ['suggested' => [$firstName, $secondName]],
            json_decode((string)$response->getContent(), true, JSON_THROW_ON_ERROR),
        );

        $duplicateResponse = $this->simulateSuggestionPost('/api/tag-suggestions', [
            'imageId' => $image->getId(),
            'tags' => "$firstName,$secondName",
        ]);
        self::assertSame(HttpStatus::CREATED, $duplicateResponse->getStatus());
        self::assertSame(
            ['suggested' => []],
            json_decode((string)$duplicateResponse->getContent(), true, JSON_THROW_ON_ERROR),
        );

        self::assertSame(
            HttpStatus::BAD_REQUEST,
            $this->simulateSuggestionPost('/api/tag-suggestions', [
                'imageId' => $image->getId(),
                'tags' => ' , ',
            ])->getStatus(),
        );
        self::assertSame(
            HttpStatus::BAD_REQUEST,
            $this->simulateSuggestionPost('/api/tag-suggestions', ['imageId' => 0, 'tags' => 'valid'])->getStatus(),
        );
        self::assertSame(
            HttpStatus::NOT_FOUND,
            $this->simulateSuggestionPost('/api/tag-suggestions', ['imageId' => 999999, 'tags' => 'valid'])->getStatus(),
        );
    }

    public function testQueueRequiresModAndReturnsOldestFirstSuggestions(): void
    {
        self::assertSame(
            HttpStatus::UNAUTHORIZED,
            $this->simulateRequest(new Request(
                url: new Url(protocol: 'https', host: 'localhost', path: '/api/tag-suggestions/queue'),
                method: HttpMethod::GET,
            ))->getStatus(),
        );

        $fellowLogin = 'queue-fellow-' . bin2hex(random_bytes(4));
        $this->loginUserWithAccessLevel($fellowLogin, 'Queue Fellow', AccessLevel::FELLOW);
        $fellowId = $this->sessionService->getUserData()->id;
        $image = ImageTestUtils::createImageEntity($this->imageEntityService, $fellowId);
        $firstName = 'queue-first-' . bin2hex(random_bytes(4));
        $secondName = 'queue-second-' . bin2hex(random_bytes(4));
        $this->tagSuggestionService->createSuggestions($image->getId(), $fellowId, [$firstName, $secondName]);

        $forbidden = $this->simulateRequest(new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/api/tag-suggestions/queue'),
            method: HttpMethod::GET,
        ));
        self::assertSame(HttpStatus::FORBIDDEN, $forbidden->getStatus());

        $this->loginUserWithAccessLevel(
            'queue-mod-' . bin2hex(random_bytes(4)),
            'Queue Mod',
            AccessLevel::MOD,
        );
        $response = $this->simulateRequest(new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/api/tag-suggestions/queue'),
            method: HttpMethod::GET,
        ));
        self::assertSame(HttpStatus::OK, $response->getStatus());
        $suggestions = json_decode((string)$response->getContent(), true, JSON_THROW_ON_ERROR)['suggestions'];
        $matching = array_values(array_filter(
            $suggestions,
            static fn(array $suggestion): bool => in_array($suggestion['tagName'], [$firstName, $secondName], true),
        ));
        self::assertSame([$firstName, $secondName], array_column($matching, 'tagName'));
        self::assertSame([
            'id' => $this->getSuggestionId($image->getId(), $firstName),
            'tagName' => $firstName,
            'imageId' => $image->getId(),
            'suggestedBy' => ['id' => $fellowId, 'displayName' => 'Queue Fellow'],
            'created' => $this->getSuggestionCreated($image->getId(), $firstName),
        ], $matching[0]);
        self::assertArrayHasKey('created', $matching[1]);
    }

    public function testApproveRequiresModAndCreatesTagAttachmentApprovalThenRemovesSuggestion(): void
    {
        self::assertSame(
            HttpStatus::UNAUTHORIZED,
            $this->simulateSuggestionAction('/api/tag-suggestions/approve', '1')->getStatus(),
        );

        $fellowLogin = 'approve-fellow-' . bin2hex(random_bytes(4));
        $this->loginUserWithAccessLevel($fellowLogin, 'Approve Fellow', AccessLevel::FELLOW);
        $suggesterId = $this->sessionService->getUserData()->id;
        $image = ImageTestUtils::createImageEntity($this->imageEntityService, $suggesterId);
        $tagName = 'approved-' . bin2hex(random_bytes(4));
        $this->tagSuggestionService->createSuggestions($image->getId(), $suggesterId, [$tagName]);
        $suggestionId = $this->getSuggestionId($image->getId(), $tagName);

        self::assertSame(
            HttpStatus::FORBIDDEN,
            $this->simulateSuggestionAction('/api/tag-suggestions/approve', (string)$suggestionId)->getStatus(),
        );
        $this->loginUserWithAccessLevel(
            'approve-mod-' . bin2hex(random_bytes(4)),
            'Approve Mod',
            AccessLevel::MOD,
        );
        $response = $this->simulateSuggestionAction('/api/tag-suggestions/approve', (string)$suggestionId);
        self::assertSame(HttpStatus::NO_CONTENT, $response->getStatus());
        self::assertNull($response->getContent());
        self::assertSame(1, $this->tagService->getUsageCount($tagName));
        self::assertContains($tagName, $this->tagService->getTagsForImage($image->getId()));

        $tagIdStatement = $this->pdo->prepare('SELECT id FROM tag WHERE name = :name COLLATE NOCASE');
        $tagIdStatement->execute(['name' => $tagName]);
        $tagId = (int)$tagIdStatement->fetchColumn();
        $approvalStatement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM tag_approval WHERE tag_id = :tag_id AND user_id = :user_id'
        );
        $approvalStatement->execute(['tag_id' => $tagId, 'user_id' => $suggesterId]);
        self::assertSame(1, (int)$approvalStatement->fetchColumn());
        self::assertSame(0, $this->countSuggestions($image->getId(), $tagName));

        self::assertSame(HttpStatus::BAD_REQUEST, $this->simulateSuggestionAction('/api/tag-suggestions/approve', 'x')->getStatus());
        self::assertSame(HttpStatus::NOT_FOUND, $this->simulateSuggestionAction('/api/tag-suggestions/approve', '999999')->getStatus());
    }

    public function testRejectRequiresModAndDeletesSuggestionWithoutCreatingApproval(): void
    {
        $fellowLogin = 'reject-fellow-' . bin2hex(random_bytes(4));
        $this->loginUserWithAccessLevel($fellowLogin, 'Reject Fellow', AccessLevel::FELLOW);
        $suggesterId = $this->sessionService->getUserData()->id;
        $image = ImageTestUtils::createImageEntity($this->imageEntityService, $suggesterId);
        $tagName = 'rejected-' . bin2hex(random_bytes(4));
        $this->tagSuggestionService->createSuggestions($image->getId(), $suggesterId, [$tagName]);
        $suggestionId = $this->getSuggestionId($image->getId(), $tagName);

        self::assertSame(
            HttpStatus::FORBIDDEN,
            $this->simulateSuggestionAction('/api/tag-suggestions/reject', (string)$suggestionId)->getStatus(),
        );
        self::assertSame(
            HttpStatus::UNAUTHORIZED,
            $this->simulateSuggestionAction('/api/tag-suggestions/reject', (string)$suggestionId, logIn: false)->getStatus(),
        );
        $this->loginUserWithAccessLevel(
            'reject-mod-' . bin2hex(random_bytes(4)),
            'Reject Mod',
            AccessLevel::MOD,
        );
        $response = $this->simulateSuggestionAction('/api/tag-suggestions/reject', (string)$suggestionId);
        self::assertSame(HttpStatus::NO_CONTENT, $response->getStatus());
        self::assertNull($response->getContent());
        self::assertSame(0, $this->countSuggestions($image->getId(), $tagName));
        self::assertNull($this->tagService->getUsageCount($tagName));
        self::assertSame(0, $this->tagApprovalService->countForUser($suggesterId));
        self::assertSame(HttpStatus::NOT_FOUND, $this->simulateSuggestionAction('/api/tag-suggestions/reject', '999999')->getStatus());
        self::assertSame(HttpStatus::BAD_REQUEST, $this->simulateSuggestionAction('/api/tag-suggestions/reject', 'bad')->getStatus());
    }

    private function simulateSuggestionPost(string $path, array $postParams): Response
    {
        return $this->simulateRequest(new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: $path),
            method: HttpMethod::POST,
            postParams: $postParams,
        ));
    }

    private function simulateSuggestionAction(string $path, string $id, bool $logIn = true): Response
    {
        if (!$logIn) {
            // Start a fresh unauthenticated session for this request.
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION = [];
            }
        }
        return $this->simulateRequest(new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: $path),
            method: HttpMethod::POST,
            getParams: ['id' => $id],
        ));
    }

    private function getSuggestionId(int $imageId, string $tagName): int
    {
        $statement = $this->pdo->prepare(
            'SELECT id FROM tag_suggestion WHERE image_id = :image_id AND tag_name = :tag_name COLLATE NOCASE'
        );
        $statement->execute(['image_id' => $imageId, 'tag_name' => $tagName]);
        return (int)$statement->fetchColumn();
    }

    private function getSuggestionCreated(int $imageId, string $tagName): string
    {
        $statement = $this->pdo->prepare(
            'SELECT created FROM tag_suggestion WHERE image_id = :image_id AND tag_name = :tag_name COLLATE NOCASE'
        );
        $statement->execute(['image_id' => $imageId, 'tag_name' => $tagName]);
        return (new DateTime((string)$statement->fetchColumn()))->format('Y-m-d H:i:s');
    }

    private function countSuggestions(int $imageId, string $tagName): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM tag_suggestion WHERE image_id = :image_id AND tag_name = :tag_name COLLATE NOCASE'
        );
        $statement->execute(['image_id' => $imageId, 'tag_name' => $tagName]);
        return (int)$statement->fetchColumn();
    }
}
