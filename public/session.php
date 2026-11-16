<?php

/**
 * Return the signed-in user for the header chip. The static catalogue pages
 * fetch this after load; it is a read-only, same-origin request.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
require dirname(__DIR__) . '/src/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$user = current_user();

echo json_encode([
    'user' => $user === null ? null : [
        'display_name' => $user['display_name'],
        'email' => $user['email'],
        'role' => $user['role'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
