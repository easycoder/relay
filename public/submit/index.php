<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';
require dirname(__DIR__, 2) . '/src/html.php';
require dirname(__DIR__, 2) . '/src/auth.php';
require dirname(__DIR__, 2) . '/src/projects.php';

$user = require_login();
$pdo = db();
$categories = $pdo->query('SELECT slug, name FROM categories ORDER BY sort_order, name')->fetchAll();

$ownerId = (int) $user['id'];
$submissionPauseUntil = null;
$listingsSlotAt = null;
$pendingCount = 0;
$listingsUsed = 0;

$errors = [];
$old = [];

$isOperator = in_array($user['role'], ['admin', 'moderator'], true);

// Anything unexpected in here is a message on the page rather than a bare 500.
// An operator sees the exception text, because diagnosing it otherwise means
// reading the server log for every failed attempt.
try {
    // Bound the queue, not just the clock: the reviewer is the scarce resource,
    // so an account may only have so many listings waiting, and only so many
    // new ones in the rolling window. A run of rejections pauses the account.
    $submissionPauseUntil = submission_pause_until($pdo, $ownerId);
    $pendingCount = pending_listings($pdo, $ownerId);
    $listingsUsed = new_listings_in_window($pdo, $ownerId);
    $listingsSlotAt = new_listing_slot_at($pdo, $ownerId);
} catch (Throwable $e) {
    error_log('Relay: submission limits failed: ' . get_class($e) . ': ' . $e->getMessage());
    app_log('submit limits: ' . get_class($e) . ': ' . $e->getMessage());
    $errors['form'] = $isOperator
        ? 'The submission limits could not be read: ' . $e->getMessage()
        : 'Submissions are not available right now. Please try again shortly.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errors === []) {
    // A long brief may arrive as an uploaded file rather than a form field: see
    // absorb_brief_upload(). Done before anything reads it.
    absorb_brief_upload($_POST, $_FILES);

    // Breadcrumb before anything can fail: if the body arrived empty or
    // truncated, this line says so in storage/error.log.
    app_log('submit: post received, brief ' . strlen((string) ($_POST['brief'] ?? '')) . ' chars');

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
            . ' listings pending approval. Wait for a decision on one of them before submitting another.';
    } elseif ($listingsUsed >= SUBMIT_WINDOW_LIMIT) {
        $errors['form'] = 'You have submitted ' . SUBMIT_WINDOW_LIMIT . ' listings in the last '
            . SUBMIT_WINDOW_DAYS . ' days. Another one is possible from '
            . format_date($listingsSlotAt) . '.';
    } else {
        [$data, $errors] = validate_project_submission($_POST, $pdo);
        $old = $_POST;

        app_log('submit: validated, ' . count($errors) . ' problem(s)');

        if ($errors === []) {
            try {
                create_project($pdo, $ownerId, $data);

                app_log('submit: saved');

                // The queue is pull-only otherwise: the operator finds out by
                // remembering to look. One email per entry, after the save.
                notify_admin(
                    'New listing pending approval — ' . $data['title'],
                    "A new listing is pending approval.\n\n"
                    . 'Title: ' . $data['title'] . "\n"
                    . 'Owner: ' . $user['display_name'] . ' <' . $user['email'] . ">\n\n"
                    . 'Review it: ' . site_url() . "/review/\n",
                    (string) $user['email']
                );

                redirect('/dashboard/?submitted=1');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('Relay: creating a listing failed: ' . get_class($e) . ': ' . $e->getMessage());
                app_log('submit save: ' . get_class($e) . ': ' . $e->getMessage());

                $errors['form'] = $isOperator
                    ? 'That listing could not be saved: ' . $e->getMessage()
                    : 'That listing could not be saved. Nothing was published; please try again.';
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
    'stages' => stage_options(),
    'statuses' => project_status_options(),
    'arrangements' => arrangement_options(),
    'revenueStates' => revenue_state_options(),
    'userScopes' => user_scope_options(),
    'readinessLevels' => readiness_options(),
    'assets' => asset_options(),
    'contactModes' => contact_mode_options(),
    'prompt' => submission_prompt(array_column($categories, 'name', 'slug')),
    'importAction' => '/submit/import/',
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
