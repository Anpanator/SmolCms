<?php

declare(strict_types=1);

$applicationRoot = getenv('APP_ROOT');
if (!is_string($applicationRoot) || $applicationRoot === '') {
    throw new RuntimeException('APP_ROOT must be configured.');
}

$databasePath = $applicationRoot . '/private/db/smolcms.sq3';
if (is_file($databasePath)) {
    exit(0);
}

$schemaPath = $applicationRoot . '/private/init/init_sqlite.sql';
$schema = file_get_contents($schemaPath);
if ($schema === false) {
    throw new RuntimeException("Could not read SQLite schema at {$schemaPath}.");
}

$pdo = new PDO('sqlite:' . $databasePath);
$pdo->exec($schema);
