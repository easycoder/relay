<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/bootstrap.php';
require dirname(__DIR__, 3) . '/src/html.php';
require dirname(__DIR__, 3) . '/src/auth.php';

$token = (string) ($_GET['token'] ?? '');
$error = 'That sign-in link has expired or has already been used.';

if ($token === '' || strlen($token) !== 64) {
    $error = 'That sign-in link is incomplete.';
} else {
    $link = consume_magic_link($token);

    if ($link !== null) {
        $userId = find_or_create_user($link['email']);
        create_session($userId);
        redirect('/dashboard/');
    }
}

$content = render_template('message', [
    'heading' => 'Sign-in link not valid',
    'message' => $error,
    'link' => '/login/',
    'linkText' => 'Request another link',
]);

echo render_site_page('Sign in — Relay', 'Sign in to Relay.', '/login/verify/', $content, true);
