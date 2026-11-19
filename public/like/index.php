<?php

/**
 * Record a reasoned like on a pending listing.
 *
 * POST only and signed in. A listing is approved here when the like
 * reaches the configured threshold, unless a reviewer has already rejected it
 * or asked for it to be seen by a person. The decision is recorded under the
 * same `review_project()` that the review queue uses, so publishing fields and
 * the audit trail cannot drift.
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';
require dirname(__DIR__, 2) . '/src/html.php';
require dirname(__DIR__, 2) . '/src/auth.php';
require dirname(__DIR__, 2) . '/src/projects.php';
require dirname(__DIR__, 2) . '/src/likes.php';

$user = require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/find/?view=pending');
}

$slug = trim((string) ($_POST['slug'] ?? ''));
$reason = trim((string) ($_POST['reason'] ?? ''));
$back = '/waiting/?slug=' . urlencode($slug);

if (!verify_csrf((string) ($_POST['csrf'] ?? ''))) {
    redirect($back . '&like=error');
}

$stmt = $pdo->prepare('SELECT * FROM projects WHERE slug = ?');
$stmt->execute([$slug]);
$project = $stmt->fetch();

if ($project === false) {
    redirect('/find/?view=pending');
}

$eligibility = endorsement_eligibility($pdo, $project, $user);

if (!$eligibility['allowed']) {
    redirect($back . '&like=' . urlencode($eligibility['reason']));
}

if (strlen($reason) < like_reason_min_length()) {
    redirect($back . '&like=short');
}

if (strlen($reason) > like_reason_max_length()) {
    $reason = substr($reason, 0, like_reason_max_length());
}

$result = record_endorsement(
    $pdo,
    (int) $project['id'],
    (int) $user['id'],
    $reason,
    client_ip_hash()
);

if (!$result['recorded']) {
    redirect($back . '&like=' . urlencode($result['reason']));
}

if ($result['approved']) {
    notify_admin(
        'Listing approved by the community — ' . $project['title'],
        "A listing reached the like threshold and was approved automatically.\n\n"
        . 'Title: ' . $project['title'] . "\n"
        . 'Likes: ' . (int) $result['count'] . "\n\n"
        . 'It will be in the catalogue after the next build. Review or veto it: '
        . site_url() . "/review/\n",
        (string) $user['email']
    );
}

redirect($back . '&like=' . ($result['approved'] ? 'approved' : 'recorded'));
