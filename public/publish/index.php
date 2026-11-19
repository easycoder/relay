<?php

/**
 * Rebuild the public catalogue from the browser.
 *
 * The same code the CLI runs, so a reviewer who approves a listing can publish it
 * without a shell. POST only: a GET must never be able to trigger a build. The
 * build lock lives in build_catalogue(), so a cron run and a button press cannot
 * interleave.
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';
require dirname(__DIR__, 2) . '/src/html.php';
require dirname(__DIR__, 2) . '/src/auth.php';
require dirname(__DIR__, 2) . '/src/build.php';

$user = require_role(['admin', 'moderator']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/review/');
}

if (!verify_csrf((string) ($_POST['csrf'] ?? ''))) {
    redirect('/review/?error=stale');
}

try {
    $result = build_catalogue();
} catch (BuildBusyException $e) {
    redirect('/review/?error=busy');
} catch (Throwable $e) {
    error_log('Relay: build failed: ' . get_class($e) . ': ' . $e->getMessage());
    app_log('publish: ' . get_class($e) . ': ' . $e->getMessage());

    redirect('/review/?error=build');
}

app_log(
    'publish: ' . $result['listings'] . ' listing(s), ' . $result['removed'] . ' removed, '
    . $result['pending'] . ' pending'
);

redirect('/review/?published=' . $result['listings'] . '&removed=' . $result['removed']);
