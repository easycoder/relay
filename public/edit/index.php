<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';
require dirname(__DIR__, 2) . '/src/html.php';
require dirname(__DIR__, 2) . '/src/auth.php';
require dirname(__DIR__, 2) . '/src/projects.php';
require dirname(__DIR__, 2) . '/src/likes.php';
require dirname(__DIR__, 2) . '/src/diff.php';

$user = require_login();
$pdo = db();
$slug = (string) ($_GET['slug'] ?? '');

$stmt = $pdo->prepare('SELECT * FROM projects WHERE slug = ?');
$stmt->execute([$slug]);
$project = $stmt->fetch();

if ($project === false) {
    http_response_code(404);
    $content = render_template('message', [
        'heading' => 'Listing not found',
        'message' => 'There is no listing with that address.',
        'link' => '/dashboard/',
        'linkText' => 'Back to the dashboard',
    ]);
    echo render_site_page('Not found — Relay', 'Listing not found.', '/edit/', $content, true);
    exit;
}

$isOwner = (int) $project['owner_id'] === (int) $user['id'];
$isModerator = in_array($user['role'], ['admin', 'moderator'], true);

if (!$isOwner && !$isModerator) {
    http_response_code(403);
    $content = render_template('message', [
        'heading' => 'Not your listing',
        'message' => 'Only the owner or a moderator can edit this listing.',
        'link' => '/dashboard/',
        'linkText' => 'Back to the dashboard',
    ]);
    echo render_site_page('Forbidden — Relay', 'You cannot edit this listing.', '/edit/', $content, true);
    exit;
}

$relations = project_relations($pdo, (int) $project['id']);
$isLive = $project['review_status'] === 'approved';

// A retry is a save that puts a non-live listing back in the queue. Count what
// this listing has already used, so endless resubmission is refused before it
// is written rather than noticed afterwards.
$resubmissionsUsed = $isLive ? 0 : resubmissions_used($pdo, (int) $project['id']);
$resubmitOpenAt = $resubmissionsUsed >= RESUBMIT_LIMIT
    ? resubmissions_open_at($pdo, (int) $project['id'])
    : null;
$resubmitBlocked = $resubmitOpenAt !== null;

// A pause after repeated rejections belongs to the owner's account, not to
// whoever is editing: a moderator fixing wording should not be blocked by it.
$submissionPauseUntil = submission_pause_until($pdo, (int) $project['owner_id']);

$old = project_form_data($project, $relations);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    absorb_brief_upload($_POST, $_FILES);

    $csrf = (string) ($_POST['csrf'] ?? '');

    if (!verify_csrf($csrf)) {
        $errors['form'] = 'The form expired. Please try again.';
    } elseif (!empty($_POST['relay_hp'])) {
        redirect('/dashboard/');
    } else {
        [$data, $errors] = validate_project_submission($_POST, $pdo);
        $old = $_POST;

        // The cap is checked before anything is written: an endless retry loop
        // gets a refusal, not a queue entry.
        if ($errors === [] && $resubmitBlocked) {
            $errors['form'] = 'This listing has been resubmitted ' . RESUBMIT_LIMIT . ' times in the last '
                . RESUBMIT_WINDOW_DAYS . ' days. It can be resubmitted again from '
                . format_date($resubmitOpenAt) . '.';
        }

        if ($errors === [] && !$isLive && $submissionPauseUntil !== null) {
            $errors['form'] = 'Submissions for this account are paused until '
                . format_date($submissionPauseUntil) . ': ' . SUBMIT_STRIKES
                . ' listings were rejected in the last ' . SUBMIT_WINDOW_DAYS . ' days.';
        }

        if ($errors === []) {
            // The diff is against the version this save replaces: the live
            // listing for an edit, the current row for a pending, rejected or
            // taken-down listing. It is stored with the submission, so a later
            // edit cannot change what the reviewer was shown. A diff failure
            // must not block the save; the review page falls back to the full
            // text.
            $reviewDiffJson = null;

            try {
                $reviewDiffJson = review_diff_to_json(
                    build_review_diff(project_form_data($project, $relations), $data)
                );
            } catch (Throwable $e) {
                app_log('edit diff: ' . get_class($e) . ': ' . $e->getMessage());
            }

            if ($isLive && $reviewDiffJson === null) {
                redirect('/edit/?slug=' . urlencode((string) $project['slug']) . '&saved=none');
            }

            $pdo->beginTransaction();

            try {
                if ($isLive) {
                    record_live_edit(
                        $pdo,
                        (int) $project['id'],
                        (int) $project['owner_id'],
                        (int) $user['id'],
                        $data,
                        (string) $reviewDiffJson
                    );

                    $pdo->commit();

                    redirect('/dashboard/?edited=1');
                }

                apply_project_data($pdo, (int) $project['id'], $data);

                // A resubmission replaces the rejection: the old reason no
                // longer describes the listing that is now waiting. Likes were given for the old content, so they go too.
                $pdo->prepare(
                    "UPDATE projects
                     SET review_status = 'pending', review_note = NULL, approval_source = NULL, review_diff = ?
                     WHERE id = ?"
                )->execute([$reviewDiffJson, $project['id']]);

                clear_project_likes($pdo, (int) $project['id']);

                record_resubmission($pdo, (int) $project['id'], (int) $user['id']);

                $audit = $pdo->prepare(
                    "INSERT INTO audit_log (actor_user_id, subject_user_id, action, entity_type, entity_id, detail, created_at)
                     VALUES (?, ?, 'project.updated', 'project', ?, ?, UTC_TIMESTAMP())"
                );
                $audit->execute([
                    $user['id'],
                    (int) $project['owner_id'],
                    $project['id'],
                    'Edited via /edit/ and returned to review',
                ]);

                $pdo->commit();

                notify_admin(
                    'Listing resubmitted for review — ' . $project['title'],
                    "A listing has been edited and has returned to the review queue.\n\n"
                    . 'Title: ' . $project['title'] . "\n"
                    . 'Edited by: ' . $user['display_name'] . ' <' . $user['email'] . ">\n\n"
                    . 'Review it: ' . site_url() . "/review/\n",
                    (string) $user['email']
                );

                redirect('/dashboard/?updated=1');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                throw $e;
            }
        }
    }
}

if ($isLive) {
    $notice = 'This listing is live. Saving applies your changes immediately; the public page updates on the next build. It is marked Edited and stays in the reviewer\'s re-review list for '
        . edit_review_days() . ' days unless accepted. A reviewer can take it down for correction.';
} elseif ($project['review_status'] === 'rejected') {
    $notice = 'This listing was rejected. Everything you entered is still here: change what you need and save, and it goes back into the review queue as a fresh submission.';
} elseif ($project['review_status'] === 'taken_down') {
    $notice = 'This listing was taken down. The reason is below. Correct it and save; it goes back to the review queue, and a person will approve it before it is published again.';
} else {
    $notice = 'This listing is not public yet. Saving updates it and returns it to the review queue.';
}

if (!$isLive && !empty($project['auto_approval_blocked'])) {
    $notice .= ' A reviewer has already rejected or taken down this listing, so a person must approve the new version: likes alone will not.';
}

if (($_GET['delete'] ?? '') === 'stale') {
    $notice = 'The delete request was out of date, so nothing was deleted. Try again.';
}

if (($_GET['saved'] ?? '') === 'none') {
    $notice = 'Nothing changed, so the listing was left as it was.';
}

$content = render_template('submit', [
    'user' => $user,
    'categories' => $pdo->query('SELECT slug, name FROM categories ORDER BY sort_order, name')->fetchAll(),
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
    'eyebrow' => 'Edit',
    'heading' => 'Edit ' . form_value($old, 'title', $project['title']),
    'intro' => $isLive
        ? 'Your changes go live immediately; the public page updates on the next build. The listing is marked Edited and stays in the reviewer\'s re-review list for '
            . edit_review_days() . ' days unless accepted.'
        : ($project['review_status'] === 'rejected'
            ? 'This listing was rejected. Your existing details are kept; saving resubmits it for review.'
            : ($project['review_status'] === 'taken_down'
                ? 'This listing was taken down. The reason is shown above; saving sends the corrected version back for review.'
                : 'This listing is not public yet. Saving returns it to the review queue.')),
    'formAction' => '/edit/?slug=' . urlencode($project['slug']),
    'submitLabel' => $isLive ? 'Save changes' : 'Save and resubmit',
    'notice' => $notice,
    'reviewNote' => (string) ($project['review_note'] ?? ''),
    'resubmissionsUsed' => $resubmissionsUsed,
    'resubmissionLimit' => RESUBMIT_LIMIT,
    'resubmitOpenAt' => $resubmitOpenAt,
    'submissionPauseUntil' => $submissionPauseUntil,
    'submissionStrikes' => SUBMIT_STRIKES,
    'deleteAction' => '/delete/',
    'deleteSlug' => (string) $project['slug'],
    'deleteBlockers' => project_delete_blockers($pdo, (int) $project['id']),
    'cancelUrl' => '/dashboard/',
]);

echo render_site_page(
    'Edit ' . $project['title'] . ' — Relay',
    'Edit a Relay listing.',
    '/edit/',
    $content,
    true
);
