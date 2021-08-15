<?php
declare(strict_types=1);

namespace SmolCms\Service\DB;

use RuntimeException;

class QueryCriteria
{
    public const TYPE_SELECT = 'SELECT';
    public const TYPE_UPDATE = 'UPDATE';
    public const TYPE_DELETE = 'DELETE';
    public const KEY_AND = 'A';
    public const KEY_OR = 'O';

    private string $type;
    private string $mainEntity;
    /** @var array<array<string, string>> */
    private array $whereConditions = [];
    private array $parameters = [];
    private ?int $offset = null;
    private ?int $limit = null;

    public function select(string $entityClass): static
    {
        $this->checkQueryTypeSet();
        $this->mainEntity = $entityClass;
        $this->type = self::TYPE_SELECT;
        return $this;
    }

    public function delete(string $entityClass): static
    {
        $this->checkQueryTypeSet();
        $this->mainEntity = $entityClass;
        $this->type = self::TYPE_DELETE;
        return $this;
    }

    public function update(string $entityClass): static
    {
        $this->checkQueryTypeSet();
        $this->mainEntity = $entityClass;
        $this->type = self::TYPE_UPDATE;
        return $this;
    }

    public function andWhere(string $condition): static
    {
        $this->whereConditions[] = [self::KEY_AND => $condition];
        return $this;
    }

    public function orWhere(string $condition): static
    {
        $this->whereConditions[] = [self::KEY_OR => $condition];
        return $this;
    }

    public function withParameters(array $params): static
    {
        $this->parameters = $params;
        return $this;
    }

    public function maxResults(int $resultCount): static
    {
        $this->limit = $resultCount;
        return $this;
    }

    public function skipResults(int $skipCount): static
    {
        $this->offset = $skipCount;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getMainEntity(): string
    {
        return $this->mainEntity;
    }

    public function getWhereConditions(): array
    {
        return $this->whereConditions;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getOffset(): ?int
    {
        return $this->offset;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    private function checkQueryTypeSet(): void
    {
        if (isset($this->type)) throw new RuntimeException("Query type is already set to: {$this->type}");
    }
}