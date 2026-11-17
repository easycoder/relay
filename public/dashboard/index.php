<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';
require dirname(__DIR__, 2) . '/src/html.php';
require dirname(__DIR__, 2) . '/src/auth.php';
require dirname(__DIR__, 2) . '/src/interests.php';

$user = require_login();
$pdo = db();

$stmt = $pdo->prepare(
    "SELECT p.*,
            (SELECT COUNT(*) FROM project_revisions r
             WHERE r.project_id = p.id AND r.review_status = 'pending') AS pending_revisions,
            (SELECT COUNT(*) FROM interests i
             WHERE i.project_id = p.id AND i.status = 'new') AS new_interests
     FROM projects p
     WHERE p.owner_id = ?
     ORDER BY p.created_at DESC, p.id DESC"
);
$stmt->execute([$user['id']]);
$projects = $stmt->fetchAll();

$interestCountStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM interests i
     JOIN projects p ON p.id = i.project_id
     WHERE p.owner_id = ? AND i.status = 'new'"
);
$interestCountStmt->execute([$user['id']]);
$newInterestCount = (int) $interestCountStmt->fetchColumn();

$myInterestsStmt = $pdo->prepare(
    'SELECT i.id, i.status, i.created_at, i.decided_at, i.decision_note,
            p.slug, p.title, p.status AS project_status,
            owner.display_name AS owner_name, owner.email AS owner_email
     FROM interests i
     JOIN projects p ON p.id = i.project_id
     JOIN users owner ON owner.id = p.owner_id
     WHERE i.user_id = ?
     ORDER BY i.created_at DESC'
);
$myInterestsStmt->execute([$user['id']]);
$myInterests = $myInterestsStmt->fetchAll();

$content = render_template('dashboard', [
    'user' => $user,
    'projects' => $projects,
    'submitted' => isset($_GET['submitted']),
    'updated' => isset($_GET['updated']),
    'revision' => isset($_GET['revision']),
    'switched' => isset($_GET['switched']),
    'signoutStale' => (($_GET['signout'] ?? '') === 'stale'),
    'csrf' => csrf_token(),
    'isReviewer' => in_array($user['role'], ['admin', 'moderator'], true),
    'newInterestCount' => $newInterestCount,
    'myInterests' => $myInterests,
    'accountCount' => count(live_remembered_accounts()),
]);

echo render_site_page('Dashboard — Relay', 'Your Relay listings.', '/dashboard/', $content, true);
