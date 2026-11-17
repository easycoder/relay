<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';
require dirname(__DIR__, 2) . '/src/html.php';
require dirname(__DIR__, 2) . '/src/auth.php';
require dirname(__DIR__, 2) . '/src/review.php';

$user = require_role(['admin', 'moderator']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf((string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Invalid request.');
    }

    $kind = (string) ($_POST['kind'] ?? 'project');
    $action = (string) ($_POST['action'] ?? '');
    $note = (string) ($_POST['note'] ?? '');

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

    $projectId = (int) ($_POST['project_id'] ?? 0);

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
    "SELECT p.*, u.display_name AS owner_name, c.name AS category_name
     FROM projects p
     JOIN users u ON u.id = p.owner_id
     JOIN categories c ON c.slug = p.category
     WHERE p.review_status = 'pending'
     ORDER BY p.created_at ASC, p.id ASC"
)->fetchAll();

$revisions = $pdo->query(
    "SELECT r.id, r.project_id, r.payload, r.created_at,
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

$content = render_template('review', [
    'user' => $user,
    'projects' => $projects,
    'revisions' => $revisions,
    'csrf' => csrf_token(),
    'done' => (string) ($_GET['done'] ?? ''),
    'error' => (string) ($_GET['error'] ?? ''),
    'published' => isset($_GET['published']) ? (int) $_GET['published'] : null,
    'removed' => (int) ($_GET['removed'] ?? 0),
]);

echo render_site_page('Review queue — Relay', 'Review pending Relay listings.', '/review/', $content, true);
