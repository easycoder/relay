#!/usr/bin/env php
<?php

/**
 * Human review queue for listings.
 *
 * Only projects with review_status = 'approved' are built into the public
 * catalogue. New submissions start pending; a trusted person runs this CLI:
 *
 *   php scripts/review.php list
 *   php scripts/review.php approve <slug>
 *   php scripts/review.php reject <slug> [--note "reason"]
 *
 * Then rebuild with php scripts/build.php.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

function usage(): void
{
    echo <<<'TXT'
Usage:
  php scripts/review.php list
  php scripts/review.php approve <slug>
  php scripts/review.php reject <slug> [--note "reason"]

Only approved listings are published. Rebuild after a decision:
  php scripts/build.php
TXT;
    echo PHP_EOL;
}

$args = array_slice($argv, 1);
$command = $args[0] ?? 'list';

if (in_array($command, ['help', '-h', '--help'], true)) {
    usage();
    exit(0);
}

if (!in_array($command, ['list', 'approve', 'reject'], true)) {
    usage();
    exit(2);
}

try {
    $pdo = db();
} catch (Throwable $e) {
    fwrite(STDERR, "Database connection failed: {$e->getMessage()}\n");
    exit(1);
}

if ($command === 'list') {
    $rows = $pdo->query(
        "SELECT id, slug, title, status, created_at
         FROM projects
         WHERE review_status = 'pending'
         ORDER BY created_at ASC, id ASC"
    )->fetchAll();

    if ($rows === []) {
        echo "No projects awaiting review.\n";
        exit(0);
    }

    foreach ($rows as $row) {
        printf("#%d  %s\n", $row['id'], $row['title']);
        printf("     slug: %s\n", $row['slug']);
        printf("     status: %s\n", $row['status']);
        printf("     submitted: %s\n\n", $row['created_at']);
    }

    echo count($rows) . " awaiting review.\n";
    exit(0);
}

$slug = $args[1] ?? '';
if ($slug === '' || str_starts_with($slug, '--')) {
    usage();
    exit(2);
}

$note = '';
$noteIndex = array_search('--note', $args, true);
if ($noteIndex !== false && isset($args[$noteIndex + 1])) {
    $note = trim((string) $args[$noteIndex + 1]);
}

$stmt = $pdo->prepare('SELECT id, slug, title, review_status FROM projects WHERE slug = ?');
$stmt->execute([$slug]);
$project = $stmt->fetch();

if (!$project) {
    fwrite(STDERR, "No project with slug {$slug}.\n");
    exit(1);
}

$reviewStatus = $command === 'approve' ? 'approved' : 'rejected';

$update = $pdo->prepare(
    "UPDATE projects
     SET review_status = ?,
         published_at = CASE
             WHEN ? = 'approved' THEN COALESCE(published_at, UTC_TIMESTAMP())
             ELSE published_at
         END
     WHERE id = ?"
);
$update->execute([$reviewStatus, $reviewStatus, $project['id']]);

$detail = 'Review via scripts/review.php by ' . (getenv('USER') ?: 'cli')
    . ($note !== '' ? ': ' . $note : '');

$audit = $pdo->prepare(
    "INSERT INTO audit_log (actor_user_id, action, entity_type, entity_id, detail, created_at)
     VALUES (NULL, ?, 'project', ?, ?, UTC_TIMESTAMP())"
);
$audit->execute(['project.' . $reviewStatus, $project['id'], $detail]);

echo ucfirst($reviewStatus) . " {$project['slug']} ({$project['title']}).\n";
echo "Run php scripts/build.php to update the public catalogue.\n";
