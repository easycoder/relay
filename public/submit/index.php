<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';
require dirname(__DIR__, 2) . '/src/html.php';
require dirname(__DIR__, 2) . '/src/auth.php';
require dirname(__DIR__, 2) . '/src/projects.php';

$user = require_login();
$pdo = db();
$categories = $pdo->query('SELECT slug, name FROM categories ORDER BY sort_order, name')->fetchAll();

$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string) ($_POST['csrf'] ?? '');

    if (!verify_csrf($csrf)) {
        $errors['form'] = 'The form expired. Please try again.';
    } elseif (!empty($_POST['relay_hp'])) {
        // Honeypot: a bot filled the hidden field. Show the normal success
        // page and write nothing.
        redirect('/dashboard/?submitted=1');
    } else {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM projects
             WHERE owner_id = ? AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)'
        );
        $stmt->execute([$user['id']]);

        if ((int) $stmt->fetchColumn() >= 3) {
            $errors['form'] = 'You have submitted several projects in the last day. Try again tomorrow.';
        } else {
            [$data, $errors] = validate_project_submission($_POST, $pdo);
            $old = $_POST;

            if ($errors === []) {
                create_project($pdo, (int) $user['id'], $data);
                redirect('/dashboard/?submitted=1');
            }
        }
    }
}

$content = render_template('submit', [
    'user' => $user,
    'categories' => $categories,
    'errors' => $errors,
    'old' => $old,
    'csrf' => csrf_token(),
    'statuses' => project_status_options(),
    'arrangements' => arrangement_options(),
    'revenueStates' => revenue_state_options(),
    'userScopes' => user_scope_options(),
    'readinessLevels' => readiness_options(),
    'assets' => asset_options(),
    'contactModes' => contact_mode_options(),
]);

echo render_site_page(
    'Submit a project — Relay',
    'Describe a project that needs a next maintainer.',
    '/submit/',
    $content,
    true
);
