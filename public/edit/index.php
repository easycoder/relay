<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';
require dirname(__DIR__, 2) . '/src/html.php';
require dirname(__DIR__, 2) . '/src/auth.php';
require dirname(__DIR__, 2) . '/src/projects.php';

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
$pendingRevision = pending_revision($pdo, (int) $project['id']);
$isLive = $project['review_status'] === 'approved';
$hasPendingRevision = $pendingRevision !== null;

$old = $hasPendingRevision
    ? project_data_to_form(revision_data($pendingRevision))
    : project_form_data($project, $relations);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string) ($_POST['csrf'] ?? '');

    if (!verify_csrf($csrf)) {
        $errors['form'] = 'The form expired. Please try again.';
    } elseif (!empty($_POST['relay_hp'])) {
        redirect('/dashboard/');
    } else {
        [$data, $errors] = validate_project_submission($_POST, $pdo);
        $old = $_POST;

        if ($errors === []) {
            $pdo->beginTransaction();

            try {
                if ($isLive) {
                    create_or_update_revision(
                        $pdo,
                        (int) $project['id'],
                        (int) $user['id'],
                        $data
                    );

                    $audit = $pdo->prepare(
                        "INSERT INTO audit_log (actor_user_id, action, entity_type, entity_id, detail, created_at)
                         VALUES (?, 'project.revision_submitted', 'project', ?, ?, UTC_TIMESTAMP())"
                    );
                    $audit->execute([$user['id'], $project['id'], 'Revision submitted via /edit/']);

                    $pdo->commit();
                    redirect('/dashboard/?revision=1');
                }

                apply_project_data($pdo, (int) $project['id'], $data);

                $pdo->prepare(
                    "UPDATE projects SET review_status = 'pending' WHERE id = ?"
                )->execute([$project['id']]);

                $audit = $pdo->prepare(
                    "INSERT INTO audit_log (actor_user_id, action, entity_type, entity_id, detail, created_at)
                     VALUES (?, 'project.updated', 'project', ?, ?, UTC_TIMESTAMP())"
                );
                $audit->execute([
                    $user['id'],
                    $project['id'],
                    'Edited via /edit/ and returned to review',
                ]);

                $pdo->commit();
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

if ($isLive && $hasPendingRevision) {
    $notice = 'You already have a revision awaiting review. Saving replaces that pending revision; the live listing stays visible.';
} elseif ($isLive) {
    $notice = 'This listing is live. Your changes are saved as a revision and reviewed before they replace it.';
} else {
    $notice = 'This listing is not public yet. Saving updates it and returns it to the review queue.';
}

$content = render_template('submit', [
    'user' => $user,
    'categories' => $pdo->query('SELECT slug, name FROM categories ORDER BY sort_order, name')->fetchAll(),
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
    'eyebrow' => 'Edit',
    'heading' => 'Edit ' . form_value($old, 'title', $project['title']),
    'intro' => $isLive
        ? 'Changes are reviewed before they replace the live listing.'
        : 'This listing is not public yet. Saving returns it to the review queue.',
    'formAction' => '/edit/?slug=' . urlencode($project['slug']),
    'submitLabel' => $isLive ? 'Save revision for review' : 'Save and resubmit',
    'notice' => $notice,
    'cancelUrl' => '/dashboard/',
]);

echo render_site_page(
    'Edit ' . $project['title'] . ' — Relay',
    'Edit a Relay listing.',
    '/edit/',
    $content,
    true
);
