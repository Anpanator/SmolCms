<?php

declare(strict_types=1);

namespace SmolCms\Service\Tag;

use DateTime;
use PDO;
use SmolCms\Data\Persistence\TagApprovalEntity;
use SmolCms\Data\Persistence\TagSuggestionEntity;
use SmolCms\Exception\PersistenceException;
use SmolCms\Service\Core\CaseConverter;
use SmolCms\Service\DB\EntityAttributeProcessor;
use SmolCms\Service\DB\EntityService;
use SmolCms\Service\DB\QueryBuilder;
use SmolCms\Service\DB\QueryCriteria;
use SmolCms\Service\DB\TagApprovalService;
use Throwable;
use InvalidArgumentException;

readonly class TagSuggestionService extends EntityService
{
    public function __construct(
        PDO $pdo,
        CaseConverter $caseConverter,
        QueryBuilder $queryBuilder,
        EntityAttributeProcessor $entityAttributeProcessor,
        private TagService $tagService,
        private TagApprovalService $tagApprovalService,
    )
    {
        parent::__construct($pdo, $caseConverter, $queryBuilder, $entityAttributeProcessor);
    }

    /**
     * Stores normalized suggestions, excluding real tags and suggestions already queued for this image.
     *
     * @param list<string> $tagNames
     * @return list<string>
     */
    public function createSuggestions(int $imageId, int $userId, array $tagNames): array
    {
        return $this->withinTransaction(function () use ($imageId, $userId, $tagNames): array {
            $storedNames = [];
            $seenNames = [];

            foreach ($tagNames as $tagName) {
                if (!is_string($tagName)) {
                    throw new InvalidArgumentException('Tag names must be strings.');
                }
                $normalizedName = TagService::normalizeName($tagName);
                if (isset($seenNames[$normalizedName])) {
                    continue;
                }
                $seenNames[$normalizedName] = true;

                if ($this->tagService->getUsageCount($normalizedName) !== null
                    || $this->findSuggestion($imageId, $normalizedName) !== null) {
                    continue;
                }

                $suggestion = new TagSuggestionEntity(
                    id: null,
                    imageId: $imageId,
                    tagName: $normalizedName,
                    userId: $userId,
                    created: new DateTime(),
                );
                try {
                    $this->saveAsNew($suggestion);
                } catch (PersistenceException $exception) {
                    if (self::isUniqueConstraintViolation($exception)) {
                        continue;
                    }
                    throw $exception;
                }
                $storedNames[] = $normalizedName;
            }

            return $storedNames;
        });
    }

    /**
     * Oldest-first moderation queue.
     *
     * @return list<array{id: int, tagName: string, imageId: int, suggestedBy: array{id: int, displayName: string}, created: string}>
     */
    public function getQueue(): array
    {
        $statement = $this->pdo->query(
            'SELECT s.id, s.tag_name, s.image_id, s.user_id, s.created, u.display_name '
            . 'FROM tag_suggestion s LEFT JOIN user u ON u.id = s.user_id '
            . 'ORDER BY s.created ASC, s.id ASC'
        );
        $queue = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $queue[] = [
                'id' => (int)$row['id'],
                'tagName' => (string)$row['tag_name'],
                'imageId' => (int)$row['image_id'],
                'suggestedBy' => [
                    'id' => (int)$row['user_id'],
                    'displayName' => (string)($row['display_name'] ?? ''),
                ],
                'created' => (new DateTime((string)$row['created']))->format('Y-m-d H:i:s'),
            ];
        }
        return $queue;
    }

    /** Approves a suggestion atomically; false means the suggestion id is unknown. */
    public function approve(int $suggestionId): bool
    {
        return $this->withinTransaction(function () use ($suggestionId): bool {
            $suggestion = $this->findSuggestionById($suggestionId);
            if ($suggestion === null) {
                return false;
            }

            $tagId = $this->tagService->findOrCreateTagId($suggestion->getTagName(), 'General');
            $this->tagService->attachTagToImage($suggestion->getImageId(), $suggestion->getTagName());
            $this->tagApprovalService->saveAsNew(new TagApprovalEntity(
                id: null,
                tagId: $tagId,
                userId: $suggestion->getUserId(),
                created: new DateTime(),
            ));
            $this->deleteSuggestion($suggestionId);
            return true;
        });
    }

    /** Rejects a suggestion; false means the suggestion id is unknown. */
    public function reject(int $suggestionId): bool
    {
        return $this->withinTransaction(function () use ($suggestionId): bool {
            if ($this->findSuggestionById($suggestionId) === null) {
                return false;
            }
            $this->deleteSuggestion($suggestionId);
            return true;
        });
    }

    private function findSuggestion(int $imageId, string $tagName): ?TagSuggestionEntity
    {
        $criteria = new QueryCriteria();
        $criteria->select(TagSuggestionEntity::class)
            ->andWhere('image_id = :image_id')
            ->andWhere('tag_name = :tag_name COLLATE NOCASE')
            ->withParameters(['image_id' => $imageId, 'tag_name' => $tagName])
            ->maxResults(1);
        return ($this->execute($criteria) ?? [])[0] ?? null;
    }

    private function findSuggestionById(int $suggestionId): ?TagSuggestionEntity
    {
        $criteria = new QueryCriteria();
        $criteria->select(TagSuggestionEntity::class)
            ->andWhere('id = :id')
            ->withParameters(['id' => $suggestionId])
            ->maxResults(1);
        return ($this->execute($criteria) ?? [])[0] ?? null;
    }

    private function deleteSuggestion(int $suggestionId): void
    {
        $criteria = new QueryCriteria();
        $criteria->delete(TagSuggestionEntity::class)
            ->andWhere('id = :id')
            ->withParameters(['id' => $suggestionId]);
        $this->execute($criteria);
    }

    private static function isUniqueConstraintViolation(Throwable $throwable): bool
    {
        for ($current = $throwable; $current !== null; $current = $current->getPrevious()) {
            $message = mb_strtolower($current->getMessage(), 'UTF-8');
            if (str_contains($message, 'unique constraint') || str_contains($message, 'duplicate entry')) {
                return true;
            }
        }
        return false;
    }
}
