<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Tag;

use PDO;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;

class TagImportMigrationTest extends FunctionalTestCase
{
    private const string IMPORT_MIGRATION = '2026-10-19_00-00-01__import_tags.sql';
    private const string CREATE_MIGRATION = '2026-10-19_00-00-00__create_tag_tables.sql';

    #[Autowire]
    private PDO $pdo;

    public function testImportMigrationContainsSanitizedUniqueTagStatements(): void
    {
        $path = ROOT_DIR . '/private/sql/' . self::IMPORT_MIGRATION;
        $sql = file_get_contents($path);
        self::assertIsString($sql);

        $pattern = "/^INSERT OR IGNORE INTO tag \\(name, category_id\\) VALUES \\('((?:''|[^'\\r\\n])*)', \\(SELECT id FROM tag_category WHERE name = '(Artist|General)'\\)\\);$/m";
        $matchCount = preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER);
        self::assertSame(6087, $matchCount);
        self::assertSame(6087, substr_count($sql, 'INSERT OR IGNORE INTO tag'));

        $seen = [];
        $categoryCounts = ['Artist' => 0, 'General' => 0];
        foreach ($matches as $match) {
            $name = str_replace("''", "'", $match[1]);
            $categoryCounts[$match[2]]++;
            self::assertNotSame('', $name);
            self::assertSame(trim($name), $name);
            self::assertStringNotContainsString('[', $name);
            self::assertStringNotContainsString(']', $name);
            self::assertStringNotContainsString('_', $name);
            self::assertNotSame('tagme', mb_strtolower($name, 'UTF-8'));
            $key = mb_strtolower($name, 'UTF-8');
            self::assertArrayNotHasKey($key, $seen, "Duplicate imported tag: $name");
            $seen[$key] = true;
        }
        self::assertSame(['Artist' => 1537, 'General' => 4550], $categoryCounts);
        self::assertContains('#compass', array_map(
            static fn(array $match): string => str_replace("''", "'", $match[1]),
            $matches
        ));
        self::assertContains('(arknights)', array_map(
            static fn(array $match): string => str_replace("''", "'", $match[1]),
            $matches
        ));
        self::assertContains('.live', array_map(
            static fn(array $match): string => str_replace("''", "'", $match[1]),
            $matches
        ));
        self::assertContains('04 (artery gear fusion)', array_map(
            static fn(array $match): string => str_replace("''", "'", $match[1]),
            $matches
        ));
    }

    public function testTagMigrationsAreIdempotentAgainstFreshInstallSchema(): void
    {
        $migrationDirectory = ROOT_DIR . '/private/sql/';
        $createSql = file_get_contents($migrationDirectory . self::CREATE_MIGRATION);
        $importSql = file_get_contents($migrationDirectory . self::IMPORT_MIGRATION);
        self::assertIsString($createSql);
        self::assertIsString($importSql);

        $this->pdo->exec($createSql);
        $this->pdo->exec($importSql);
        $firstCount = (int)$this->pdo->query('SELECT COUNT(*) FROM tag')->fetchColumn();
        $this->pdo->exec($createSql);
        $this->pdo->exec($importSql);
        $secondCount = (int)$this->pdo->query('SELECT COUNT(*) FROM tag')->fetchColumn();

        self::assertSame(6088, $firstCount);
        self::assertSame($firstCount, $secondCount);
    }
}
