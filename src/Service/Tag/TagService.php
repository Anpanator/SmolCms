<?php

declare(strict_types=1);

namespace SmolCms\Service\Tag;

use InvalidArgumentException;
use RuntimeException;
use SmolCms\Data\Persistence\ImageEntity;
use SmolCms\Data\Persistence\ImageTagEntity;
use SmolCms\Data\Persistence\TagCategoryEntity;
use SmolCms\Data\Persistence\TagEntity;
use SmolCms\Exception\PersistenceException;
use SmolCms\Service\DB\EntityService;
use SmolCms\Service\DB\QueryCriteria;
use Throwable;

/**
 * Tag and tag-category persistence, including the maintained image usage counter.
 */
readonly class TagService extends EntityService
{
    private const string DEFAULT_CATEGORY = 'General';
    private const int BROWSE_PER_PAGE = 25;
    private const int SUGGEST_LIMIT = 15;

    /** Normalizes and validates a tag name using Unicode lowercase folding. */
    public static function normalizeName(string $name): string
    {
        $normalized = self::normalizeTagName($name);
        self::validateName($normalized);
        return $normalized;
    }

    /** Lowercase-folds a tag name and normalizes whitespace, without validation. */
    public static function normalizeTagName(string $name): string
    {
        return mb_strtolower(self::collapseWhitespace($name), 'UTF-8');
    }

    /**
     * @return array{name: string, category: string, usageCount: int}|null Null means duplicate name.
     */
    public function createTag(string $name, ?string $categoryName = null): ?array
    {
        $normalizedName = self::normalizeName($name);
        $normalizedCategory = self::normalizeCategoryName($categoryName ?? self::DEFAULT_CATEGORY);

        return $this->withinTransaction(function () use ($normalizedName, $normalizedCategory): ?array {
            $category = $this->findCategory($normalizedCategory);
            if ($category === null) {
                throw new InvalidArgumentException("Unknown tag category: $normalizedCategory");
            }
            if ($this->findTag($normalizedName) !== null) {
                return null;
            }

            try {
                $this->saveAsNew(new TagEntity(null, $normalizedName, $category->getId()));
            } catch (PersistenceException $exception) {
                if (self::isUniqueConstraintViolation($exception)) {
                    return null;
                }
                throw $exception;
            }

            return [
                'name' => $normalizedName,
                'category' => $category->getName(),
                'usageCount' => 0,
            ];
        });
    }

    /**
     * @return string|null Null means duplicate name.
     */
    public function createCategory(string $name): ?string
    {
        $normalizedName = self::normalizeCategoryName($name);

        return $this->withinTransaction(function () use ($normalizedName): ?string {
            if ($this->findCategory($normalizedName) !== null) {
                return null;
            }

            try {
                $this->saveAsNew(new TagCategoryEntity(null, $normalizedName));
            } catch (PersistenceException $exception) {
                if (self::isUniqueConstraintViolation($exception)) {
                    return null;
                }
                throw $exception;
            }

            return $normalizedName;
        });
    }

    /**
     * Ensures a tag exists and returns its ID. This is used by uploads and supports tags
     * created outside the moderation API as well.
     */
    public function findOrCreateTagId(string $name, ?string $categoryName = null): int
    {
        $normalizedName = self::normalizeName($name);
        $normalizedCategory = self::normalizeCategoryName($categoryName ?? self::DEFAULT_CATEGORY);

        return $this->withinTransaction(function () use ($normalizedName, $normalizedCategory): int {
            $tag = $this->findTag($normalizedName);
            if ($tag !== null) {
                return $tag->getId();
            }

            $category = $this->findCategory($normalizedCategory);
            if ($category === null) {
                throw new InvalidArgumentException("Unknown tag category: $normalizedCategory");
            }

            try {
                $this->saveAsNew(new TagEntity(null, $normalizedName, $category->getId()));
            } catch (PersistenceException $exception) {
                if (!self::isUniqueConstraintViolation($exception)) {
                    throw $exception;
                }
            }

            $tag = $this->findTag($normalizedName);
            if ($tag === null) {
                throw new RuntimeException('Could not find or create the requested tag.');
            }
            return $tag->getId();
        });
    }

    /**
     * Attaches a tag to an existing image. Repeated attachment is a no-op and never
     * increments usage_count more than once for the image/tag pair.
     */
    public function attachTagToImage(int $imageId, string $tagName): bool
    {
        if ($imageId < 1) {
            return false;
        }
        $normalizedName = self::normalizeName($tagName);

        return $this->withinTransaction(function () use ($imageId, $normalizedName): bool {
            if (!$this->imageExists($imageId)) {
                return false;
            }

            $tagId = $this->findOrCreateTagId($normalizedName);
            try {
                $this->saveAsNew(new ImageTagEntity($imageId, $tagId));
            } catch (PersistenceException $exception) {
                if (self::isUniqueConstraintViolation($exception)) {
                    return false;
                }
                throw $exception;
            }

            $tag = $this->findTagById($tagId);
            if ($tag === null) {
                throw new RuntimeException('Could not find the tag that was just attached.');
            }
            $tag->setUsageCount($tag->getUsageCount() + 1);
            $this->update($tag);
            return true;
        });
    }

    /**
     * Removes one image/tag association and decrements usage_count only when it existed.
     */
    public function detachTagFromImage(int $imageId, string $tagName): bool
    {
        if ($imageId < 1) {
            return false;
        }
        $normalizedName = self::normalizeName($tagName);

        return $this->withinTransaction(function () use ($imageId, $normalizedName): bool {
            $tag = $this->findTag($normalizedName);
            if ($tag === null) {
                return false;
            }

            $associationCriteria = new QueryCriteria();
            $associationCriteria->delete(ImageTagEntity::class)
                ->andWhere('image_id = :image_id')
                ->andWhere('tag_id = :tag_id')
                ->withParameters(['image_id' => $imageId, 'tag_id' => $tag->getId()]);
            $statement = $this->prepare($associationCriteria);
            $statement->execute();
            if ($statement->rowCount() === 0) {
                return false;
            }

            $this->decrementUsageCount($tag);
            return true;
        });
    }

    /**
     * Removes all associations for an image and returns the number removed.
     */
    public function detachAllFromImage(int $imageId): int
    {
        return $this->withinTransaction(function () use ($imageId): int {
            $this->decrementUsageCounts($this->getTagsAttachedToImage($imageId));

            $associationCriteria = new QueryCriteria();
            $associationCriteria->delete(ImageTagEntity::class)
                ->andWhere('image_id = :image_id')
                ->withParameters(['image_id' => $imageId]);
            $statement = $this->prepare($associationCriteria);
            $statement->execute();
            return $statement->rowCount();
        });
    }

    /**
     * Deleting tagme is prohibited whether addressed by name or ID.
     */
    public function deleteTag(string $name): bool
    {
        $normalizedName = self::normalizeName($name);
        return $this->withinTransaction(function () use ($normalizedName): bool {
            $tag = $this->findTag($normalizedName);
            return $tag !== null && $this->deleteTagEntityWithinTransaction($tag);
        });
    }

    public function deleteTagByName(string $name): bool
    {
        return $this->deleteTag($name);
    }

    public function deleteTagById(int $tagId): bool
    {
        return $this->withinTransaction(
            fn(): bool => $tagId > 0 && $this->deleteTagByIdWithinTransaction($tagId)
        );
    }

    /**
     * @return list<string>
     */
    public function getCategoryNames(): array
    {
        $criteria = new QueryCriteria();
        $criteria->select(TagCategoryEntity::class)
            ->orderBy('name COLLATE NOCASE')
            ->orderBy('name');
        $categories = $this->execute($criteria) ?? [];
        return array_map(
            static fn(TagCategoryEntity $category): string => $category->getName(),
            $categories
        );
    }

    /**
     * @return list<string>
     */
    public function getTagsForImage(int $imageId): array
    {
        $tags = $this->getTagsAttachedToImage($imageId);
        return array_map(static fn(TagEntity $tag): string => $tag->getName(), $tags);
    }

    /**
     * Public, case-insensitive prefix suggestions ordered by usage and name.
     *
     * @return list<string>
     */
    public function suggestTags(string $prefix): array
    {
        $normalizedPrefix = self::normalizeTagName($prefix);
        if ($normalizedPrefix === '') {
            return [];
        }

        $criteria = new QueryCriteria();
        $criteria->select(TagEntity::class)
            ->andWhere("name COLLATE NOCASE LIKE :prefix ESCAPE '\\'")
            ->withParameters(['prefix' => self::escapeLikePattern($normalizedPrefix) . '%'])
            ->orderBy('usage_count', QueryCriteria::ORDER_DESC)
            ->orderBy('name COLLATE NOCASE')
            ->maxResults(self::SUGGEST_LIMIT);
        $tags = $this->execute($criteria) ?? [];
        return array_map(static fn(TagEntity $tag): string => $tag->getName(), $tags);
    }

    /**
     * Moderation browse results, including a filtered total and page metadata.
     *
     * @return array{tags: list<array{name: string, category: string, usageCount: int}>, page: int, perPage: int, total: int}
     */
    public function browseTags(int $page = 1, string $query = ''): array
    {
        if ($page < 1 || $page > intdiv(PHP_INT_MAX, self::BROWSE_PER_PAGE) + 1) {
            throw new InvalidArgumentException('Page must be a positive integer.');
        }

        $query = self::normalizeTagName($query);

        $countCriteria = new QueryCriteria();
        $countCriteria->select(TagEntity::class);
        self::applyBrowseFilter($countCriteria, $query);
        $total = $this->countResults($countCriteria);

        $pageCriteria = new QueryCriteria();
        $pageCriteria->select(TagEntity::class)
            ->orderBy('usage_count', QueryCriteria::ORDER_DESC)
            ->orderBy('name COLLATE NOCASE')
            ->maxResults(self::BROWSE_PER_PAGE)
            ->skipResults(($page - 1) * self::BROWSE_PER_PAGE);
        self::applyBrowseFilter($pageCriteria, $query);
        $tags = $this->execute($pageCriteria) ?? [];

        return [
            'tags' => $this->withCategoryNames($tags),
            'page' => $page,
            'perPage' => self::BROWSE_PER_PAGE,
            'total' => $total,
        ];
    }

    /**
     * Counts tags only across the images on the current image-list page.
     *
     * @param list<int> $imageIds
     * @return list<array{name: string, imageCount: int}>
     */
    public function getTopTagsForImages(array $imageIds, int $limit = 25): array
    {
        $uniqueIds = [];
        foreach ($imageIds as $imageId) {
            if (is_int($imageId) && $imageId > 0) {
                $uniqueIds[$imageId] = $imageId;
            }
        }
        if ($uniqueIds === [] || $limit < 1) {
            return [];
        }

        // image_tag rows are unique per image/tag pair, so per-tag row counts
        // equal the distinct image counts
        $usageCounts = [];
        foreach ($this->findAssociationsByImageIds(array_values($uniqueIds)) as $association) {
            $tagId = $association->getTagId();
            $usageCounts[$tagId] = ($usageCounts[$tagId] ?? 0) + 1;
        }
        if ($usageCounts === []) {
            return [];
        }

        $tags = $this->findTagsByIds(array_keys($usageCounts));
        usort($tags, static function (TagEntity $a, TagEntity $b) use ($usageCounts): int {
            $countDifference = $usageCounts[$b->getId()] <=> $usageCounts[$a->getId()];
            if ($countDifference !== 0) {
                return $countDifference;
            }
            // strcasecmp mirrors the case-insensitive (ASCII) NOCASE collation,
            // strcmp breaks remaining ties exactly like the second ORDER BY term
            return strcasecmp($a->getName(), $b->getName()) ?: strcmp($a->getName(), $b->getName());
        });

        $result = [];
        foreach (array_slice($tags, 0, $limit) as $tag) {
            $result[] = [
                'name' => $tag->getName(),
                'imageCount' => $usageCounts[$tag->getId()],
            ];
        }
        return $result;
    }

    public function getUsageCount(string $tagName): ?int
    {
        $normalizedName = self::normalizeName($tagName);
        return $this->findTag($normalizedName)?->getUsageCount();
    }

    private function findTag(string $name): ?TagEntity
    {
        $criteria = new QueryCriteria();
        $criteria->select(TagEntity::class)
            ->andWhere('name = :name COLLATE NOCASE')
            ->withParameters(['name' => $name])
            ->maxResults(1);
        return ($this->execute($criteria) ?? [])[0] ?? null;
    }

    private function findTagById(int $tagId): ?TagEntity
    {
        $criteria = new QueryCriteria();
        $criteria->select(TagEntity::class)
            ->andWhere('id = :tag_id')
            ->withParameters(['tag_id' => $tagId])
            ->maxResults(1);
        return ($this->execute($criteria) ?? [])[0] ?? null;
    }

    private function findCategory(string $name): ?TagCategoryEntity
    {
        $criteria = new QueryCriteria();
        $criteria->select(TagCategoryEntity::class)
            ->andWhere('name = :name COLLATE NOCASE')
            ->withParameters(['name' => $name])
            ->maxResults(1);
        return ($this->execute($criteria) ?? [])[0] ?? null;
    }

    /**
     * @param list<int> $tagIds
     * @return array<TagEntity>
     */
    private function findTagsByIds(array $tagIds): array
    {
        if ($tagIds === []) {
            return [];
        }
        [$placeholders, $parameters] = $this->buildInClause('tag_id', $tagIds);
        $criteria = new QueryCriteria();
        $criteria->select(TagEntity::class)
            ->andWhere('id IN (' . implode(', ', $placeholders) . ')')
            ->withParameters($parameters)
            ->orderBy('name COLLATE NOCASE')
            ->orderBy('name');
        return $this->execute($criteria) ?? [];
    }

    /**
     * @param list<int> $categoryIds
     * @return array<TagCategoryEntity>
     */
    private function findCategoriesByIds(array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }
        [$placeholders, $parameters] = $this->buildInClause('category_id', $categoryIds);
        $criteria = new QueryCriteria();
        $criteria->select(TagCategoryEntity::class)
            ->andWhere('id IN (' . implode(', ', $placeholders) . ')')
            ->withParameters($parameters)
            ->orderBy('name COLLATE NOCASE')
            ->orderBy('name');
        return $this->execute($criteria) ?? [];
    }

    /**
     * @param list<int> $imageIds
     * @return array<ImageTagEntity>
     */
    private function findAssociationsByImageIds(array $imageIds): array
    {
        [$placeholders, $parameters] = $this->buildInClause('image_id', $imageIds);
        $criteria = new QueryCriteria();
        $criteria->select(ImageTagEntity::class)
            ->andWhere('image_id IN (' . implode(', ', $placeholders) . ')')
            ->withParameters($parameters);
        return $this->execute($criteria) ?? [];
    }

    /** @return array<TagEntity> tags attached to the image, ordered by name */
    private function getTagsAttachedToImage(int $imageId): array
    {
        $associationCriteria = new QueryCriteria();
        $associationCriteria->select(ImageTagEntity::class)
            ->andWhere('image_id = :image_id')
            ->withParameters(['image_id' => $imageId]);
        $associations = $this->execute($associationCriteria) ?? [];
        if ($associations === []) {
            return [];
        }

        $tagIds = [];
        foreach ($associations as $association) {
            $tagIds[$association->getTagId()] = $association->getTagId();
        }
        return $this->findTagsByIds(array_values($tagIds));
    }

    private function imageExists(int $imageId): bool
    {
        $criteria = new QueryCriteria();
        $criteria->select(ImageEntity::class)
            ->andWhere('id = :image_id')
            ->withParameters(['image_id' => $imageId])
            ->maxResults(1);
        return ($this->execute($criteria) ?? []) !== [];
    }

    /** Decrements the usage counter, never letting it drop below zero. */
    private function decrementUsageCount(TagEntity $tag): void
    {
        if ($tag->getUsageCount() < 1) {
            return;
        }
        $tag->setUsageCount($tag->getUsageCount() - 1);
        $this->update($tag);
    }

    /**
     * Bulk-decrements the usage counters through the entity save mechanism within
     * one transaction.
     *
     * @param array<TagEntity> $tags
     */
    private function decrementUsageCounts(array $tags): void
    {
        foreach ($tags as $tag) {
            if ($tag->getUsageCount() > 0) {
                $tag->setUsageCount($tag->getUsageCount() - 1);
            }
        }
        $this->saveAll($tags);
    }

    /**
     * @param array<TagEntity> $tags
     * @return list<array{name: string, category: string, usageCount: int}>
     */
    private function withCategoryNames(array $tags): array
    {
        if ($tags === []) {
            return [];
        }

        $categoryIds = [];
        foreach ($tags as $tag) {
            $categoryIds[$tag->getCategoryId()] = $tag->getCategoryId();
        }
        $categoryNames = [];
        foreach ($this->findCategoriesByIds(array_values($categoryIds)) as $category) {
            $categoryNames[$category->getId()] = $category->getName();
        }

        $result = [];
        foreach ($tags as $tag) {
            // missing categories behave like the INNER JOIN they replace: the row is dropped
            $categoryName = $categoryNames[$tag->getCategoryId()] ?? null;
            if ($categoryName === null) {
                continue;
            }
            $result[] = [
                'name' => $tag->getName(),
                'category' => $categoryName,
                'usageCount' => $tag->getUsageCount(),
            ];
        }
        return $result;
    }

    private function deleteTagByIdWithinTransaction(int $tagId): bool
    {
        $tag = $this->findTagById($tagId);
        return $tag !== null && $this->deleteTagEntityWithinTransaction($tag);
    }

    private function deleteTagEntityWithinTransaction(TagEntity $tag): bool
    {
        if (mb_strtolower($tag->getName(), 'UTF-8') === 'tagme') {
            return false;
        }

        $associationCriteria = new QueryCriteria();
        $associationCriteria->delete(ImageTagEntity::class)
            ->andWhere('tag_id = :tag_id')
            ->withParameters(['tag_id' => $tag->getId()]);
        $this->execute($associationCriteria);

        $tagCriteria = new QueryCriteria();
        $tagCriteria->delete(TagEntity::class)
            ->andWhere('id = :tag_id')
            ->withParameters(['tag_id' => $tag->getId()]);
        $statement = $this->prepare($tagCriteria);
        $statement->execute();
        return $statement->rowCount() > 0;
    }

    private static function applyBrowseFilter(QueryCriteria $criteria, string $query): void
    {
        if ($query === '') {
            return;
        }
        $criteria->andWhere("name COLLATE NOCASE LIKE :query ESCAPE '\\'")
            ->withParameters(['query' => '%' . self::escapeLikePattern($query) . '%']);
    }

    /**
     * @param list<int> $values
     * @return array{0: list<string>, 1: array<string, int>}
     */
    private static function buildInClause(string $parameterPrefix, array $values): array
    {
        $placeholders = [];
        $parameters = [];
        foreach (array_values($values) as $index => $value) {
            $parameterName = "{$parameterPrefix}_$index";
            $placeholders[] = ":$parameterName";
            $parameters[$parameterName] = $value;
        }
        return [$placeholders, $parameters];
    }

    private static function normalizeCategoryName(string $name): string
    {
        $normalized = self::collapseWhitespace($name);
        self::validateName($normalized);
        return $normalized;
    }

    private static function collapseWhitespace(string $name): string
    {
        $collapsed = preg_replace('/[\s\p{Z}]+/u', ' ', $name);
        if ($collapsed === null) {
            throw new InvalidArgumentException('Name must be valid UTF-8.');
        }
        return trim($collapsed);
    }

    private static function validateName(string $name): void
    {
        $length = mb_strlen($name, 'UTF-8');
        if ($length < 1 || $length > 100 || str_contains($name, ',')) {
            throw new InvalidArgumentException('Name must be 1–100 characters and must not contain a comma.');
        }
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

    private static function escapeLikePattern(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
