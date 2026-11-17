<?php

/**
 * Rebuild the public catalogue from the browser.
 *
 * The same code the CLI runs, so a reviewer who approves a listing can publish it
 * without a shell. POST only: a GET must never be able to trigger a build.
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

// One build at a time. Writing two catalogues at once would interleave the
// directory pruning with the page writes.
$dir = dirname(__DIR__, 2) . '/storage';

if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
    $dir = rtrim(sys_get_temp_dir(), '/');
}

$handle = @fopen($dir . '/publish.lock', 'c');

if ($handle === false || !@flock($handle, LOCK_EX | LOCK_NB)) {
    if (is_resource($handle)) {
        @fclose($handle);
    }

    redirect('/review/?error=busy');
}

try {
    $result = build_catalogue();
} catch (Throwable $e) {
    error_log('Relay: build failed: ' . get_class($e) . ': ' . $e->getMessage());
    app_log('publish: ' . get_class($e) . ': ' . $e->getMessage());

    @flock($handle, LOCK_UN);
    @fclose($handle);

    redirect('/review/?error=build');
}

@flock($handle, LOCK_UN);
@fclose($handle);

app_log(
    'publish: ' . $result['listings'] . ' listing(s), ' . $result['removed'] . ' removed, '
    . $result['pending'] . ' pending'
);

redirect('/review/?published=' . $result['listings'] . '&removed=' . $result['removed']);
