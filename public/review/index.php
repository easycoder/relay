<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';
require dirname(__DIR__, 2) . '/src/html.php';
require dirname(__DIR__, 2) . '/src/auth.php';
require dirname(__DIR__, 2) . '/src/review.php';
require dirname(__DIR__, 2) . '/src/likes.php';
require dirname(__DIR__, 2) . '/src/diff.php';

$user = require_role(['admin', 'moderator']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf((string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Invalid request.');
    }

    $kind = (string) ($_POST['kind'] ?? 'project');
    $action = (string) ($_POST['action'] ?? '');
    $note = trim((string) ($_POST['note'] ?? ''));
    $projectId = (int) ($_POST['project_id'] ?? 0);

    if ($kind === 'project' && $action === 'veto') {
        if ($projectId > 0 && $note !== '') {
            try {
                veto_project($pdo, $projectId, $note, (int) $user['id'], (string) $user['display_name']);
                redirect('/review/?done=veto');
            } catch (Throwable $e) {
                app_log('review veto: ' . get_class($e) . ': ' . $e->getMessage());
                redirect('/review/?error=veto');
            }
        }

        redirect('/review/?error=veto-note');
    }

    if ($kind === 'project' && $action === 'accept-edit') {
        if ($projectId > 0) {
            try {
                accept_edit($pdo, $projectId, (int) $user['id'], (string) $user['display_name']);
                redirect('/review/?done=edit-accepted');
            } catch (Throwable $e) {
                app_log('review accept edit: ' . get_class($e) . ': ' . $e->getMessage());
                redirect('/review/?error=edit-accepted');
            }
        }

        redirect('/review/');
    }

    if ($kind === 'project' && $action === 'takedown') {
        if ($projectId > 0 && $note !== '') {
            try {
                take_down_project($pdo, $projectId, $note, (int) $user['id'], (string) $user['display_name']);
                redirect('/review/?done=taken-down');
            } catch (Throwable $e) {
                app_log('review take down: ' . get_class($e) . ': ' . $e->getMessage());
                redirect('/review/?error=takedown');
            }
        }

        redirect('/review/?error=takedown-note');
    }

    if ($kind === 'revision') {
        $revisionId = (int) ($_POST['revision_id'] ?? 0);

        if ($revisionId > 0 && in_array($action, ['approve', 'reject'], true)) {
            try {
                review_revision(
                    $pdo,
                    $revisionId,
                    $action === 'approve' ? 'approved' : 'rejected',
                    $note,
                    (int) $user['id'],
                    (string) $user['display_name']
                );

                redirect('/review/?done=revision-' . $action);
            } catch (RuntimeException $e) {
                redirect('/review/?error=revision');
            }
        }

        redirect('/review/');
    }

    if ($projectId > 0 && in_array($action, ['approve', 'reject'], true)) {
        $stmt = $pdo->prepare('SELECT id FROM projects WHERE id = ?');
        $stmt->execute([$projectId]);

        if ($stmt->fetchColumn() !== false) {
            review_project(
                $pdo,
                $projectId,
                $action === 'approve' ? 'approved' : 'rejected',
                $note,
                (int) $user['id'],
                (string) $user['display_name']
            );

            redirect('/review/?done=' . $action);
        }
    }

    redirect('/review/');
}

$projects = $pdo->query(
    "SELECT p.*, u.display_name AS owner_name, c.name AS category_name,
            (SELECT COUNT(*) FROM project_likes l WHERE l.project_id = p.id) AS like_count
     FROM projects p
     JOIN users u ON u.id = p.owner_id
     JOIN categories c ON c.slug = p.category
     WHERE p.review_status = 'pending'
     ORDER BY p.created_at ASC, p.id ASC"
)->fetchAll();

$recentApprovals = $pdo->query(
    "SELECT p.*, u.display_name AS owner_name, c.name AS category_name,
            (SELECT COUNT(*) FROM project_likes l WHERE l.project_id = p.id) AS like_count
     FROM projects p
     JOIN users u ON u.id = p.owner_id
     JOIN categories c ON c.slug = p.category
     WHERE p.review_status = 'approved'
       AND p.approval_source = 'community'
       AND p.published_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)
     ORDER BY p.published_at DESC, p.id DESC"
)->fetchAll();

$recentEdits = $pdo->query(
    "SELECT p.*, u.display_name AS owner_name, c.name AS category_name,
            (SELECT COUNT(*) FROM project_likes l WHERE l.project_id = p.id) AS like_count
     FROM projects p
     JOIN users u ON u.id = p.owner_id
     JOIN categories c ON c.slug = p.category
     WHERE p.review_status = 'approved'
       AND p.edit_review_status = 'pending'
       AND p.edited_at IS NOT NULL
       AND p.edited_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL " . edit_review_days() . " DAY)
     ORDER BY p.edited_at DESC, p.id DESC"
)->fetchAll();

$revisions = $pdo->query(
    "SELECT r.id, r.project_id, r.payload, r.diff, r.created_at,
            p.slug, p.title AS project_title,
            u.display_name AS owner_name
     FROM project_revisions r
     JOIN projects p ON p.id = r.project_id
     JOIN users u ON u.id = r.created_by
     WHERE r.review_status = 'pending'
     ORDER BY r.created_at ASC, r.id ASC"
)->fetchAll();

foreach ($revisions as &$revision) {
    $revision['data'] = revision_data($revision);
}
unset($revision);

// Small queue: one query per listing is simpler than grouping a join, and it
// gives the reviewer the reasons behind a like, not just a count.
$endorsements = [];

foreach (array_merge($projects, $recentApprovals) as $project) {
    $endorsements[(int) $project['id']] = project_endorsements($pdo, (int) $project['id']);
}

$content = render_template('review', [
    'user' => $user,
    'projects' => $projects,
    'recentEdits' => $recentEdits,
    'editReviewDays' => edit_review_days(),
    'recentApprovals' => $recentApprovals,
    'endorsements' => $endorsements,
    'likeThreshold' => like_threshold(),
    'revisions' => $revisions,
    'csrf' => csrf_token(),
    'done' => (string) ($_GET['done'] ?? ''),
    'error' => (string) ($_GET['error'] ?? ''),
    'published' => isset($_GET['published']) ? (int) $_GET['published'] : null,
    'removed' => (int) ($_GET['removed'] ?? 0),
]);

echo render_site_page('Review queue — Relay', 'Review pending listings, live edits and community approvals.', '/review/', $content, true);
