<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';
require dirname(__DIR__, 2) . '/src/html.php';
require dirname(__DIR__, 2) . '/src/auth.php';
require dirname(__DIR__, 2) . '/src/projects.php';
require dirname(__DIR__, 2) . '/src/interests.php';

$user = require_login();
$pdo = db();
$slug = (string) ($_GET['project'] ?? '');
$sent = isset($_GET['sent']);
$errors = [];
$old = [];

$stmt = $pdo->prepare(
    "SELECT p.*, owner.display_name AS owner_name, owner.email AS owner_email
     FROM projects p
     JOIN users owner ON owner.id = p.owner_id
     WHERE p.slug = ?
       AND p.review_status = 'approved'
       AND p.status NOT IN ('draft', 'archived')
       AND p.published_at IS NOT NULL
       AND p.published_at <= UTC_TIMESTAMP()
       AND (p.expires_at IS NULL OR p.expires_at > UTC_TIMESTAMP())"
);
$stmt->execute([$slug]);
$project = $stmt->fetch();

$notOpen = $project === false;
$ownListing = false;
$existing = null;

if (!$notOpen) {
    $ownListing = (int) $project['owner_id'] === (int) $user['id'];

    if (!$ownListing) {
        $existing = find_interest($pdo, (int) $project['id'], (int) $user['id']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf((string) ($_POST['csrf'] ?? ''))) {
        $errors['form'] = 'The form expired. Please try again.';
    } elseif (!empty($_POST['relay_hp'])) {
        redirect('/interest/?project=' . urlencode($slug) . '&sent=1');
    } elseif ($notOpen || $ownListing || $existing !== null) {
        $errors['form'] = 'This expression of interest cannot be submitted.';
    } else {
        $rate = $pdo->prepare(
            'SELECT COUNT(*) FROM interests
             WHERE user_id = ? AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)'
        );
        $rate->execute([$user['id']]);

        if ((int) $rate->fetchColumn() >= 5) {
            $errors['form'] = 'You have sent several expressions in the last day. Try again tomorrow.';
        } else {
            [$data, $errors] = validate_interest_submission($_POST);
            $old = $_POST;

            if ($errors === []) {
                try {
                    create_interest($pdo, (int) $project['id'], (int) $user['id'], $data);
                    notify_owner_of_interest($pdo, (int) $project['id']);
                    redirect('/interest/?project=' . urlencode($slug) . '&sent=1');
                } catch (PDOException $e) {
                    $errors['form'] = 'You have already expressed interest in this project.';
                }
            }
        }
    }
}

$content = render_template('interest', [
    'project' => $project,
    'user' => $user,
    'existing' => $existing,
    'notOpen' => $notOpen,
    'ownListing' => $ownListing,
    'sent' => $sent,
    'errors' => $errors,
    'old' => $old,
    'csrf' => csrf_token(),
]);

echo render_site_page(
    'Express interest — Relay',
    'Express interest in a Relay project.',
    '/interest/',
    $content,
    true
);
