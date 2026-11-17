#!/usr/bin/env php
<?php

/**
 * Verify the database connection and the basics of the schema.
 *
 * Usage: php scripts/db-check.php
 * Config: config.php (or the file named by RELAY_CONFIG).
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

try {
    $configPath = relay_config_path();
    $config = relay_config();
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    fwrite(STDERR, "Copy config.example.php to config.php and fill in the password.\n");
    exit(1);
}

$db = $config['db'] ?? [];

foreach (['host', 'name', 'user', 'pass'] as $key) {
    if (!array_key_exists($key, $db)) {
        fwrite(STDERR, "Config is missing db.{$key}.\n");
        exit(1);
    }
}

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $db['host'],
    $db['port'] ?? 3306,
    $db['name'],
    $db['charset'] ?? 'utf8mb4'
);

try {
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("SET time_zone = '+00:00'");
} catch (PDOException $e) {
    fwrite(STDERR, "Connection failed: {$e->getMessage()}\n");
    exit(1);
}

$serverVersion = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
$time = $pdo->query('SELECT @@session.time_zone AS tz, NOW() AS now, UTC_TIMESTAMP() AS utc')->fetch();
$charset = $pdo->query('SELECT @@character_set_database AS charset, @@collation_database AS collation')->fetch();

// SHOW TABLE STATUS is used rather than information_schema because the
// engine and collation columns there are not reliably labelled in lower case
// across MySQL versions.
$tableStatus = $pdo->query('SHOW TABLE STATUS')->fetchAll(PDO::FETCH_ASSOC);
$tables = count($tableStatus);

$engines = [];
$collations = [];
foreach ($tableStatus as $row) {
    $engine = (string) ($row['Engine'] ?? $row['engine'] ?? '');
    $engine = $engine === '' ? 'unknown' : $engine;
    $engines[$engine] = ($engines[$engine] ?? 0) + 1;

    $collation = (string) ($row['Collation'] ?? $row['collation'] ?? '');
    $collation = $collation === '' ? 'unknown' : $collation;
    $collations[$collation] = ($collations[$collation] ?? 0) + 1;
}

$problems = [];

if (!str_starts_with(strtolower((string) $charset['charset']), 'utf8mb4')) {
    $problems[] = "database charset is {$charset['charset']}, expected utf8mb4";
}

foreach ($engines as $engine => $count) {
    if (strcasecmp($engine, 'InnoDB') !== 0) {
        $problems[] = "{$count} table(s) use {$engine}, expected InnoDB";
    }
}

foreach ($collations as $collation => $count) {
    if (!str_starts_with(strtolower($collation), 'utf8mb4')) {
        $problems[] = "{$count} table(s) use {$collation}, expected utf8mb4";
    }
}

if ($tables === 0) {
    $problems[] = 'no tables found — load db/schema.sql first';
}

printf("Config:   %s\n", $configPath);
printf("Server:   %s\n", $serverVersion);
printf("Time:     %s (NOW %s, UTC %s)\n", $time['tz'], $time['now'], $time['utc']);
printf("Database: %s\n", $db['name']);
printf("Charset:  %s (%s)\n", $charset['charset'], $charset['collation']);
printf("Tables:   %d\n", $tables);

if ($tables > 0) {
    $projectCount = (int) $pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn();
    printf("Projects: %d\n", $projectCount);

    $reviewColumn = $pdo->query("SHOW COLUMNS FROM projects LIKE 'review_status'")->fetch();
    if ($reviewColumn !== false) {
        $pending = (int) $pdo->query(
            "SELECT COUNT(*) FROM projects WHERE review_status = 'pending'"
        )->fetchColumn();
        printf("Pending:  %d\n", $pending);
    } else {
        echo "Review:   migration 0001 not applied\n";
    }

    // Say plainly which migrations have landed: a missing column or table is
    // the usual reason a page fails after a deploy.
    $checks = [
        '0001 projects.review_status' => ["SHOW COLUMNS FROM projects LIKE 'review_status'", 'column'],
        '0004 projects.review_note' => ["SHOW COLUMNS FROM projects LIKE 'review_note'", 'column'],
        '0005 magic_links.display_name' => ["SHOW COLUMNS FROM magic_links LIKE 'display_name'", 'column'],
    ];

    foreach ($checks as $label => [$statement, $kind]) {
        $row = $pdo->query($statement)->fetch();
        printf("Schema:   %s %s\n", $label, $row === false ? 'MISSING' : 'present');
    }

    $tablesExpected = [
        'interests' => '0003',
        'project_revisions' => '0002',
        'project_resubmissions' => '0004',
        'project_expiry_reminders' => '0006',
    ];

    $existing = [];

    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $existing[(string) $table] = true;
    }

    foreach ($tablesExpected as $table => $migration) {
        printf("Schema:   %s (%s) %s\n", $table, $migration, isset($existing[$table]) ? 'present' : 'MISSING');
    }
}

foreach ($engines as $engine => $count) {
    printf("Engine:   %s × %d\n", $engine, $count);
}

foreach ($collations as $collation => $count) {
    printf("Collation: %s × %d\n", $collation, $count);
}

if ($problems === []) {
    echo "OK\n";
    exit(0);
}

foreach ($problems as $problem) {
    fwrite(STDERR, "FAIL: {$problem}\n");
}

exit(1);
