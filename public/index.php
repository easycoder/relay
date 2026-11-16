<?php

/**
 * Front page for the generated catalogue.
 *
 * scripts/build.php writes public/home.html. This file is the stable entry
 * point; it stays small so the generated HTML can be replaced on every build.
 */

declare(strict_types=1);

$home = __DIR__ . '/home.html';

header('Content-Type: text/html; charset=utf-8');

if (!is_file($home)) {
    http_response_code(503);
    echo '<!doctype html><html lang="en-GB"><head><meta charset="utf-8"><title>Relay</title></head>'
        . '<body><h1>Relay</h1><p>The catalogue has not been built yet. '
        . 'Run <code>php scripts/build.php</code> on the server.</p></body></html>';
    exit;
}

readfile($home);
