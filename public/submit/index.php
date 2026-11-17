<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';
require dirname(__DIR__, 2) . '/src/html.php';
require dirname(__DIR__, 2) . '/src/auth.php';
require dirname(__DIR__, 2) . '/src/projects.php';

$user = require_login();
$pdo = db();
$categories = $pdo->query('SELECT slug, name FROM categories ORDER BY sort_order, name')->fetchAll();

// Bound the queue, not just the clock: the reviewer is the scarce resource, so
// an account may only have so many listings waiting, and only so many new ones
// in the rolling window. A run of rejections pauses the account outright.
$ownerId = (int) $user['id'];
$submissionPauseUntil = submission_pause_until($pdo, $ownerId);
$pendingCount = pending_listings($pdo, $ownerId);
$listingsUsed = new_listings_in_window($pdo, $ownerId);
$listingsSlotAt = new_listing_slot_at($pdo, $ownerId);

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
    } elseif ($submissionPauseUntil !== null) {
        $errors['form'] = 'Submissions are paused for this account until '
            . format_date($submissionPauseUntil) . ': ' . SUBMIT_STRIKES
            . ' listings were rejected in the last ' . SUBMIT_WINDOW_DAYS . ' days.';
    } elseif ($pendingCount >= SUBMIT_PENDING_LIMIT) {
        $errors['form'] = 'You already have ' . SUBMIT_PENDING_LIMIT
            . ' listings waiting for review. Wait for a decision on one of them before submitting another.';
    } elseif ($listingsUsed >= SUBMIT_WINDOW_LIMIT) {
        $errors['form'] = 'You have submitted ' . SUBMIT_WINDOW_LIMIT . ' listings in the last '
            . SUBMIT_WINDOW_DAYS . ' days. Another one is possible from '
            . format_date($listingsSlotAt) . '.';
    } else {
        [$data, $errors] = validate_project_submission($_POST, $pdo);
        $old = $_POST;

        if ($errors === []) {
            create_project($pdo, $ownerId, $data);
            redirect('/dashboard/?submitted=1');
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
    'submissionPauseUntil' => $submissionPauseUntil,
    'submissionStrikes' => SUBMIT_STRIKES,
    'pendingLimit' => SUBMIT_PENDING_LIMIT,
    'pendingCount' => $pendingCount,
    'listingsLimit' => SUBMIT_WINDOW_LIMIT,
    'listingsUsed' => $listingsUsed,
    'listingsWindowDays' => SUBMIT_WINDOW_DAYS,
]);

echo render_site_page(
    'Submit a project — Relay',
    'Describe a project that needs a next maintainer.',
    '/submit/',
    $content,
    true
);
