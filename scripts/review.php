#!/usr/bin/env php
<?php

/**
 * Human review queue for listings.
 *
 * New submissions and corrections start pending. Edits to live listings go
 * live immediately and appear as recent edits until accepted or the re-review
 * window passes. A trusted person runs this CLI:
 *
 *   php scripts/review.php list
 *   php scripts/review.php approve <slug>
 *   php scripts/review.php reject <slug> [--note "reason"]
 *   php scripts/review.php veto <slug> --note "reason"
 *   php scripts/review.php accept-edit <slug>
 *   php scripts/review.php takedown <slug> --note "reason"
 *
 * A veto reverses a community approval; a take-down removes a live listing
 * pending a correction and emails the owner. Neither counts as a rejection
 * strike. Then rebuild with php scripts/build.php.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
require dirname(__DIR__) . '/src/review.php';

function usage(): void
{
    echo <<<'TXT'
Usage:
  php scripts/review.php list
  php scripts/review.php approve <slug>
  php scripts/review.php reject <slug> [--note "reason"]
  php scripts/review.php veto <slug> --note "reason"
  php scripts/review.php accept-edit <slug>
  php scripts/review.php takedown <slug> --note "reason"

New listings and corrections need review. Edits to live listings go live
immediately and appear under recent edits.
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

if (!in_array($command, ['list', 'approve', 'reject', 'veto', 'accept-edit', 'takedown'], true)) {
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
        "SELECT p.id, p.slug, p.title, p.status, p.stage, p.created_at,
                (SELECT COUNT(*) FROM project_likes l WHERE l.project_id = p.id) AS like_count
         FROM projects p
         WHERE p.review_status = 'pending'
         ORDER BY p.created_at ASC, p.id ASC"
    )->fetchAll();

    $edits = $pdo->query(
        "SELECT p.slug, p.title, p.edited_at, u.display_name AS owner_name
         FROM projects p
         JOIN users u ON u.id = p.owner_id
         WHERE p.review_status = 'approved'
           AND p.edit_review_status = 'pending'
           AND p.edited_at IS NOT NULL
           AND p.edited_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL " . edit_review_days() . " DAY)
         ORDER BY p.edited_at DESC, p.id DESC"
    )->fetchAll();

    if ($rows === [] && $edits === []) {
        echo "Nothing is awaiting review.\n";
        exit(0);
    }

    if ($rows !== []) {
        foreach ($rows as $row) {
            printf("#%d  %s\n", $row['id'], $row['title']);
            printf("     slug: %s\n", $row['slug']);
            printf("     stage: %s\n", $row['stage']);
            printf("     status: %s\n", $row['status']);
            printf("     endorsements: %d\n", $row['like_count']);
            printf("     submitted: %s\n\n", $row['created_at']);
        }

        echo count($rows) . " awaiting initial review.\n";
    }

    if ($edits !== []) {
        echo PHP_EOL . "Recent edits awaiting re-review:\n";

        foreach ($edits as $edit) {
            printf(
                "  %s  %s  (edited %s, owner %s)\n",
                $edit['slug'],
                $edit['title'],
                $edit['edited_at'],
                $edit['owner_name']
            );
        }

        echo count($edits) . " edit(s) in the re-review window.\n";
    }

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

$actor = 'cli:' . (getenv('USER') ?: 'unknown');

if ($command === 'veto') {
    if ($note === '') {
        fwrite(STDERR, "A veto needs --note \"reason\".\n");
        exit(2);
    }

    try {
        veto_project($pdo, (int) $project['id'], $note, null, $actor);
    } catch (Throwable $e) {
        fwrite(STDERR, "Could not veto {$project['slug']}: {$e->getMessage()}\n");
        exit(1);
    }

    echo "Vetoed {$project['slug']} ({$project['title']}).\n";
    echo "Run php scripts/build.php to update the public catalogue.\n";
    exit(0);
}

if ($command === 'takedown') {
    if ($note === '') {
        fwrite(STDERR, "A take-down needs --note \"reason\".\n");
        exit(2);
    }

    try {
        take_down_project($pdo, (int) $project['id'], $note, null, $actor);
    } catch (Throwable $e) {
        fwrite(STDERR, "Could not take down {$project['slug']}: {$e->getMessage()}\n");
        exit(1);
    }

    echo "Took down {$project['slug']} ({$project['title']}). The owner has been emailed.\n";
    echo "Run php scripts/build.php to remove the page from the catalogue.\n";
    exit(0);
}

if ($command === 'accept-edit') {
    try {
        accept_edit($pdo, (int) $project['id'], null, $actor);
    } catch (Throwable $e) {
        fwrite(STDERR, "Could not accept the edit on {$project['slug']}: {$e->getMessage()}\n");
        exit(1);
    }

    echo "Accepted the edit on {$project['slug']} ({$project['title']}).\n";
    exit(0);
}

$reviewStatus = $command === 'approve' ? 'approved' : 'rejected';

review_project(
    $pdo,
    (int) $project['id'],
    $reviewStatus,
    $note,
    null,
    $actor
);

echo ucfirst($reviewStatus) . " {$project['slug']} ({$project['title']}).\n";
echo "Run php scripts/build.php to update the public catalogue.\n";
