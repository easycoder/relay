<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';
require dirname(__DIR__, 2) . '/src/html.php';
require dirname(__DIR__, 2) . '/src/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/');
}

if (!verify_csrf((string) ($_POST['csrf'] ?? ''))) {
    // The page was rendered for an older session. Send the user back to the
    // dashboard, which will render a fresh form and a fresh token.
    redirect('/dashboard/?signout=stale');
}

destroy_session();
redirect('/');
