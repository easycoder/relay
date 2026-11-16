<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';
require dirname(__DIR__, 2) . '/src/html.php';
require dirname(__DIR__, 2) . '/src/auth.php';
require dirname(__DIR__, 2) . '/src/interests.php';

$user = require_login();
$pdo = db();
$filterSlug = (string) ($_GET['project'] ?? '');
$done = (string) ($_GET['done'] ?? '');
$error = (string) ($_GET['error'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf((string) ($_POST['csrf'] ?? ''))) {
        redirect('/interests/?error=form');
    }

    $interestId = (int) ($_POST['interest_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    $note = (string) ($_POST['note'] ?? '');

    if ($interestId > 0 && in_array($action, ['accept', 'decline'], true)) {
        try {
            decide_interest(
                $pdo,
                $interestId,
                (int) $user['id'],
                $action === 'accept' ? 'accepted' : 'declined',
                $note
            );

            $suffix = $filterSlug !== '' ? '&project=' . urlencode($filterSlug) : '';
            redirect('/interests/?done=' . $action . $suffix);
        } catch (RuntimeException $e) {
            redirect('/interests/?error=interest');
        }
    }

    redirect('/interests/');
}

$sql = 'SELECT i.*, p.slug, p.title AS project_title, p.owner_id,
               applicant.display_name AS applicant_name, applicant.email AS applicant_email
        FROM interests i
        JOIN projects p ON p.id = i.project_id
        JOIN users applicant ON applicant.id = i.user_id
        WHERE p.owner_id = ?';
$params = [$user['id']];

if ($filterSlug !== '') {
    $sql .= ' AND p.slug = ?';
    $params[] = $filterSlug;
}

$sql .= " ORDER BY (i.status = 'new') DESC, i.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$interests = $stmt->fetchAll();

$content = render_template('interests', [
    'user' => $user,
    'interests' => $interests,
    'csrf' => csrf_token(),
    'done' => $done,
    'error' => $error,
    'filterSlug' => $filterSlug,
]);

echo render_site_page(
    'Expressions of interest — Relay',
    'People who want to take on one of your projects.',
    '/interests/',
    $content,
    true
);
