<?php

declare(strict_types=1);

namespace SmolCms\Service\Tag;

use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Tag and tag-category persistence, including the maintained image usage counter.
 */
readonly class TagService
{
    private const string DEFAULT_CATEGORY = 'General';
    private const int BROWSE_PER_PAGE = 25;

    public function __construct(
        private PDO $pdo,
    )
    {
    }

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
            if ($this->findTagId($normalizedName) !== null) {
                return null;
            }

            try {
                $statement = $this->pdo->prepare(
                    'INSERT INTO tag (name, category_id) VALUES (:name, :category_id)'
                );
                $statement->execute([
                    'name' => $normalizedName,
                    'category_id' => $category['id'],
                ]);
            } catch (PDOException $exception) {
                if (self::isUniqueConstraintViolation($exception)) {
                    return null;
                }
                throw $exception;
            }

            return [
                'name' => $normalizedName,
                'category' => $category['name'],
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
                $statement = $this->pdo->prepare('INSERT INTO tag_category (name) VALUES (:name)');
                $statement->execute(['name' => $normalizedName]);
            } catch (PDOException $exception) {
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
            $tagId = $this->findTagId($normalizedName);
            if ($tagId !== null) {
                return $tagId;
            }

            $category = $this->findCategory($normalizedCategory);
            if ($category === null) {
                throw new InvalidArgumentException("Unknown tag category: $normalizedCategory");
            }

            $statement = $this->pdo->prepare(
                'INSERT OR IGNORE INTO tag (name, category_id) VALUES (:name, :category_id)'
            );
            $statement->execute([
                'name' => $normalizedName,
                'category_id' => $category['id'],
            ]);

            $tagId = $this->findTagId($normalizedName);
            if ($tagId === null) {
                throw new RuntimeException('Could not find or create the requested tag.');
            }
            return $tagId;
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
            $imageStatement = $this->pdo->prepare('SELECT 1 FROM image WHERE id = :image_id LIMIT 1');
            $imageStatement->execute(['image_id' => $imageId]);
            if ($imageStatement->fetchColumn() === false) {
                return false;
            }

            $tagId = $this->findOrCreateTagId($normalizedName);
            $statement = $this->pdo->prepare(
                'INSERT OR IGNORE INTO image_tag (image_id, tag_id) VALUES (:image_id, :tag_id)'
            );
            $statement->execute([
                'image_id' => $imageId,
                'tag_id' => $tagId,
            ]);
            if ($statement->rowCount() === 0) {
                return false;
            }

            $usageStatement = $this->pdo->prepare(
                'UPDATE tag SET usage_count = usage_count + 1 WHERE id = :tag_id'
            );
            $usageStatement->execute(['tag_id' => $tagId]);
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
            $tagId = $this->findTagId($normalizedName);
            if ($tagId === null) {
                return false;
            }

            $statement = $this->pdo->prepare(
                'DELETE FROM image_tag WHERE image_id = :image_id AND tag_id = :tag_id'
            );
            $statement->execute([
                'image_id' => $imageId,
                'tag_id' => $tagId,
            ]);
            if ($statement->rowCount() === 0) {
                return false;
            }

            $usageStatement = $this->pdo->prepare(
                'UPDATE tag SET usage_count = CASE WHEN usage_count > 0 THEN usage_count - 1 ELSE 0 END '
                . 'WHERE id = :tag_id'
            );
            $usageStatement->execute(['tag_id' => $tagId]);
            return true;
        });
    }

    /**
     * Removes all associations for an image and returns the number removed.
     */
    public function detachAllFromImage(int $imageId): int
    {
        return $this->withinTransaction(function () use ($imageId): int {
            $usageStatement = $this->pdo->prepare(
                'UPDATE tag SET usage_count = CASE WHEN usage_count > 0 THEN usage_count - 1 ELSE 0 END '
                . 'WHERE id IN (SELECT tag_id FROM image_tag WHERE image_id = :image_id)'
            );
            $usageStatement->execute(['image_id' => $imageId]);

            $deleteStatement = $this->pdo->prepare('DELETE FROM image_tag WHERE image_id = :image_id');
            $deleteStatement->execute(['image_id' => $imageId]);
            return $deleteStatement->rowCount();
        });
    }

    /**
     * Deleting tagme is prohibited whether addressed by name or ID.
     */
    public function deleteTag(string $name): bool
    {
        $normalizedName = self::normalizeName($name);
        return $this->withinTransaction(function () use ($normalizedName): bool {
            $tagId = $this->findTagId($normalizedName);
            return $tagId !== null && $this->deleteTagByIdWithinTransaction($tagId);
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
        $statement = $this->pdo->query(
            'SELECT name FROM tag_category ORDER BY name COLLATE NOCASE ASC, name ASC'
        );
        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * @return list<string>
     */
    public function getTagsForImage(int $imageId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT t.name FROM tag t '
            . 'INNER JOIN image_tag it ON it.tag_id = t.id '
            . 'WHERE it.image_id = :image_id '
            . 'ORDER BY t.name COLLATE NOCASE ASC, t.name ASC'
        );
        $statement->execute(['image_id' => $imageId]);
        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
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

        $escapedPrefix = self::escapeLikePattern($normalizedPrefix) . '%';
        $statement = $this->pdo->prepare(
            "SELECT name FROM tag WHERE name COLLATE NOCASE LIKE :prefix ESCAPE '\\' "
            . 'ORDER BY usage_count DESC, name COLLATE NOCASE ASC LIMIT 15'
        );
        $statement->execute(['prefix' => $escapedPrefix]);
        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
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
        $where = '';
        $parameters = [];
        if ($query !== '') {
            $where = " WHERE t.name COLLATE NOCASE LIKE :query ESCAPE '\\'";
            $parameters['query'] = '%' . self::escapeLikePattern($query) . '%';
        }

        $countStatement = $this->pdo->prepare('SELECT COUNT(*) FROM tag t' . $where);
        $countStatement->execute($parameters);
        $total = (int)$countStatement->fetchColumn();

        $statement = $this->pdo->prepare(
            'SELECT t.name, c.name AS category, t.usage_count '
            . 'FROM tag t INNER JOIN tag_category c ON c.id = t.category_id'
            . $where
            . ' ORDER BY t.usage_count DESC, t.name COLLATE NOCASE ASC'
            . ' LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value, PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', self::BROWSE_PER_PAGE, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * self::BROWSE_PER_PAGE, PDO::PARAM_INT);
        $statement->execute();

        $tags = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $tags[] = [
                'name' => (string)$row['name'],
                'category' => (string)$row['category'],
                'usageCount' => (int)$row['usage_count'],
            ];
        }

        return [
            'tags' => $tags,
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

        $placeholders = [];
        $parameters = [];
        foreach (array_values($uniqueIds) as $index => $imageId) {
            $parameterName = 'image_id_' . $index;
            $placeholders[] = ':' . $parameterName;
            $parameters[$parameterName] = $imageId;
        }

        $statement = $this->pdo->prepare(
            'SELECT t.name, COUNT(DISTINCT it.image_id) AS image_count '
            . 'FROM image_tag it INNER JOIN tag t ON t.id = it.tag_id '
            . 'WHERE it.image_id IN (' . implode(', ', $placeholders) . ') '
            . 'GROUP BY t.id '
            . 'ORDER BY image_count DESC, t.name COLLATE NOCASE ASC, t.name ASC '
            . 'LIMIT :limit'
        );
        foreach ($parameters as $name => $imageId) {
            $statement->bindValue(':' . $name, $imageId, PDO::PARAM_INT);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        $tags = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $tags[] = [
                'name' => (string)$row['name'],
                'imageCount' => (int)$row['image_count'],
            ];
        }
        return $tags;
    }

    public function getUsageCount(string $tagName): ?int
    {
        $normalizedName = self::normalizeName($tagName);
        $statement = $this->pdo->prepare(
            'SELECT usage_count FROM tag WHERE name = :name COLLATE NOCASE LIMIT 1'
        );
        $statement->execute(['name' => $normalizedName]);
        $usageCount = $statement->fetchColumn();
        return $usageCount === false ? null : (int)$usageCount;
    }

    /** @return array{id: int, name: string}|null */
    private function findCategory(string $name): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name FROM tag_category WHERE name = :name COLLATE NOCASE LIMIT 1'
        );
        $statement->execute(['name' => $name]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        return ['id' => (int)$row['id'], 'name' => (string)$row['name']];
    }

    private function findTagId(string $name): ?int
    {
        $statement = $this->pdo->prepare(
            'SELECT id FROM tag WHERE name = :name COLLATE NOCASE LIMIT 1'
        );
        $statement->execute(['name' => $name]);
        $tagId = $statement->fetchColumn();
        return $tagId === false ? null : (int)$tagId;
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

    private function deleteTagByIdWithinTransaction(int $tagId): bool
    {
        $statement = $this->pdo->prepare('SELECT name FROM tag WHERE id = :tag_id LIMIT 1');
        $statement->execute(['tag_id' => $tagId]);
        $tagName = $statement->fetchColumn();
        if ($tagName === false || mb_strtolower((string)$tagName, 'UTF-8') === 'tagme') {
            return false;
        }

        $associationStatement = $this->pdo->prepare('DELETE FROM image_tag WHERE tag_id = :tag_id');
        $associationStatement->execute(['tag_id' => $tagId]);
        $deleteStatement = $this->pdo->prepare('DELETE FROM tag WHERE id = :tag_id');
        $deleteStatement->execute(['tag_id' => $tagId]);
        return $deleteStatement->rowCount() > 0;
    }

    private static function isUniqueConstraintViolation(PDOException $exception): bool
    {
        $message = mb_strtolower($exception->getMessage(), 'UTF-8');
        return str_contains($message, 'unique constraint') || str_contains($message, 'duplicate entry');
    }

    private static function escapeLikePattern(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function withinTransaction(callable $operation): mixed
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $result = $operation();
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return $result;
        } catch (Throwable $throwable) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $throwable;
        }
    }
}
