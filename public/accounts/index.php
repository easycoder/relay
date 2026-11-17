<?php

/**
 * The accounts this browser can switch between.
 *
 * Switching uses one of the recorded sessions in the `relay_accounts` cookie,
 * so it costs a click rather than another email. The list only ever holds
 * session tokens this browser already had; an entry whose session has lapsed
 * is dropped here rather than being carried around.
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';
require dirname(__DIR__, 2) . '/src/html.php';
require dirname(__DIR__, 2) . '/src/auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string) ($_POST['csrf'] ?? '');
    $action = (string) ($_POST['action'] ?? '');
    $index = filter_var($_POST['account'] ?? null, FILTER_VALIDATE_INT);

    if (!verify_login_csrf($csrf)) {
        $error = 'The form expired. Reload this page and try again.';
    } elseif ($action === 'switch') {
        $accounts = live_remembered_accounts();
        $target = $index === false ? null : ($accounts[$index] ?? null);

        if ($target !== null && switch_account((string) $target['token']) !== null) {
            redirect('/dashboard/?switched=1');
        }

        save_remembered_accounts(live_remembered_accounts());
        $error = 'That account is no longer signed in. Sign in again to restore it.';
    } elseif ($action === 'forget') {
        $accounts = live_remembered_accounts();
        $target = $index === false ? null : ($accounts[$index] ?? null);

        if ($target !== null) {
            forget_account((string) $target['token']);
        }

        redirect('/accounts/');
    } elseif ($action === 'forget-browser') {
        forget_browser();
        redirect('/accounts/?forgotten=1');
    }
}

$accounts = live_remembered_accounts();

// Drop entries whose session lapsed while they sat here, so the list only
// offers accounts that would actually work.
save_remembered_accounts($accounts);

$content = render_template('accounts', [
    'user' => current_user(),
    'accounts' => $accounts,
    'csrf' => login_csrf_token(),
    'error' => $error,
    'forgotten' => isset($_GET['forgotten']),
]);

echo render_site_page(
    'Accounts — Relay',
    'Switch between the accounts signed in on this browser.',
    '/accounts/',
    $content,
    true
);
