#!/usr/bin/env php
<?php

/**
 * Email owners whose published listings are about to expire.
 *
 *   php scripts/expiry-reminders.php [--days=14] [--dry-run]
 *
 * Run it from cron, once a day. One reminder goes out per listing per expiry:
 * `project_expiry_reminders` records what was sent, and a reconfirmation moves
 * the expiry, which makes the listing eligible again next cycle. A listing that
 * does find a home is left alone — it leaves the catalogue when it expires.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
require dirname(__DIR__) . '/src/html.php';
require dirname(__DIR__) . '/src/auth.php';
require dirname(__DIR__) . '/src/projects.php';

$days = EXPIRY_REMINDER_DAYS;
$dryRun = false;

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--dry-run') {
        $dryRun = true;
        continue;
    }

    if (str_starts_with($argument, '--days=')) {
        $days = max(1, (int) substr($argument, 7));
        continue;
    }

    fwrite(STDERR, "Usage: php scripts/expiry-reminders.php [--days=14] [--dry-run]\n");
    exit(1);
}

try {
    $pdo = db();
} catch (Throwable $e) {
    fwrite(STDERR, "Database connection failed: {$e->getMessage()}\n");
    exit(1);
}

$due = projects_due_expiry_reminder($pdo, $days);

if ($due === []) {
    echo "No listings expire in the next {$days} days.\n";
    exit(0);
}

$sent = 0;
$failed = 0;

foreach ($due as $project) {
    $expiry = format_date((string) $project['expires_at']);
    $label = $project['title'] . ' (' . $project['slug'] . ') → ' . $project['owner_email'] . ', expires ' . $expiry;

    if ($dryRun) {
        echo '[dry run] would remind: ' . $label . PHP_EOL;
        continue;
    }

    $token = create_magic_link(
        (string) $project['owner_email'],
        '',
        'reconfirm',
        (int) $project['id'],
        EXPIRY_LINK_MINUTES
    );

    $body = 'Hello ' . $project['owner_name'] . ',' . PHP_EOL . PHP_EOL
        . '"' . $project['title'] . '" is published on Relay and expires on ' . $expiry . '.' . PHP_EOL
        . 'When it expires it leaves the catalogue on the next build, and adopters' . PHP_EOL
        . 'can no longer find it.' . PHP_EOL . PHP_EOL
        . 'If it is still looking for a next maintainer, confirm it here:' . PHP_EOL . PHP_EOL
        . '  ' . reconfirm_url($token) . PHP_EOL . PHP_EOL
        . 'That keeps it published for another six months. The link works once and' . PHP_EOL
        . 'stays valid for 30 days. If it has already lapsed, sign in and use the' . PHP_EOL
        . 'listing on your dashboard: ' . site_url() . '/dashboard/' . PHP_EOL . PHP_EOL
        . 'If the project has found a home, nothing is needed: it will leave the' . PHP_EOL
        . 'catalogue on its own when it expires.' . PHP_EOL;

    if (!send_email((string) $project['owner_email'], 'Your Relay listing expires on ' . $expiry, $body)) {
        fwrite(STDERR, 'Could not send the reminder for ' . $label . PHP_EOL);
        $failed++;
        continue;
    }

    mark_expiry_reminder_sent($pdo, (int) $project['id'], (string) $project['expires_at']);
    echo 'Reminded: ' . $label . PHP_EOL;
    $sent++;
}

echo PHP_EOL . 'Sent ' . $sent . ', failed ' . $failed . ', due ' . count($due) . '.' . PHP_EOL;

exit($failed > 0 ? 1 : 0);
