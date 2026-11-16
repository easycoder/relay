#!/usr/bin/env php
<?php

/**
 * Print a magic sign-in link without sending email.
 *
 * This is for the maintainer on the server, when mail() is unavailable or
 * when testing the sign-in flow:
 *
 *   php scripts/login-link.php hello@relay.eclecity.net
 *
 * It writes a normal single-use link to the database. Open it within 15
 * minutes. It does not create the user until the link is used.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
require dirname(__DIR__) . '/src/auth.php';

$email = strtolower(trim((string) ($argv[1] ?? '')));
$name = trim((string) ($argv[2] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
    fwrite(STDERR, "Usage: php scripts/login-link.php <email> [name]\n");
    fwrite(STDERR, "The name is required for a new account.\n");
    exit(1);
}

try {
    $user = user_by_email($email);

    if ($user === null) {
        if ($name === '') {
            fwrite(STDERR, "New account: add a name, for example:\n");
            fwrite(STDERR, "  php scripts/login-link.php {$email} \"Their name\"\n");
            exit(1);
        }

        create_user($email, $name);
    }

    $token = create_magic_link($email);
} catch (Throwable $e) {
    fwrite(STDERR, "Could not create link: {$e->getMessage()}\n");
    exit(1);
}

echo magic_link_url($token) . PHP_EOL;
