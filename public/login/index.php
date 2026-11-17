<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';
require dirname(__DIR__, 2) . '/src/html.php';
require dirname(__DIR__, 2) . '/src/auth.php';

// This page is reachable while signed in, on purpose: signing in again adds a
// second account to this browser rather than replacing the first, and the
// accounts page points here for exactly that.

$signedIn = current_user();
$errors = [];
$email = '';
$name = '';
$sent = isset($_GET['sent']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $rawName = $_POST['name'] ?? '';
    $name = is_scalar($rawName) ? trim((string) $rawName) : '';
    $csrf = (string) ($_POST['csrf'] ?? '');

    if (!verify_login_csrf($csrf)) {
        $errors[] = 'The form expired. Try again.';
    } elseif (!empty($_POST['relay_hp'])) {
        // Honeypot: show the normal success message and send nothing.
        redirect('/login/?sent=1');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $errors[] = 'Enter a valid email address.';
    } else {
        $pdo = db();

        $ipCount = $pdo->prepare(
            'SELECT COUNT(*) FROM magic_links
             WHERE requested_ip_hash = ? AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)'
        );
        $ipCount->execute([client_ip_hash()]);

        $emailCount = $pdo->prepare(
            'SELECT COUNT(*) FROM magic_links
             WHERE email = ? AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)'
        );
        $emailCount->execute([$email]);

        if ((int) $ipCount->fetchColumn() >= 10 || (int) $emailCount->fetchColumn() >= 5) {
            $errors[] = 'Too many sign-in requests. Wait a few minutes and try again.';
        } else {
            $user = user_by_email($email);

            if ($user === null && $name === '') {
                $errors[] = 'Enter your name so we know what to call you.';
            } else {
                // Nothing is created here. The name rides on the link and the
                // account appears only when that link is used, so a request
                // for an address nobody can read leaves no user row behind.
                $token = create_magic_link($email, $name);

                if (!send_magic_link($email, $token)) {
                    error_log('Relay: mail() failed while sending a magic link.');
                }

                redirect('/login/?sent=1');
            }
        }
    }
}

$content = render_template('login', [
    'errors' => $errors,
    'email' => $email,
    'name' => $name,
    'sent' => $sent,
    'csrf' => login_csrf_token(),
    'accounts' => live_remembered_accounts(),
    'signedIn' => $signedIn,
]);

echo render_site_page(
    'Sign in — Relay',
    'Sign in to Relay with a single-use email link.',
    '/login/',
    $content,
    true
);
