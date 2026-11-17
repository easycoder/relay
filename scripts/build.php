#!/usr/bin/env php
<?php

/**
 * Build the public catalogue from the database.
 *
 * A thin wrapper: the work lives in src/build.php so the web app can run it from
 * the Publish button on /review/ as well as from the shell.
 *
 *   php scripts/build.php
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
require dirname(__DIR__) . '/src/html.php';
require dirname(__DIR__) . '/src/build.php';

try {
    $result = build_catalogue();
} catch (Throwable $e) {
    fwrite(STDERR, 'Build failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

foreach ($result['lines'] as $line) {
    echo $line . PHP_EOL;
}

exit(0);
