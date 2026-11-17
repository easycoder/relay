<?php

/**
 * Delete a listing.
 *
 * POST only, owner or moderator. A listing is refused if anyone is interacting
 * with it: an expression of interest (in any state) or a revision waiting for
 * review. Everything hanging off the listing cascades; the audit trail does
 * not, so the deletion stays visible after the row has gone.
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';
require dirname(__DIR__, 2) . '/src/html.php';
require dirname(__DIR__, 2) . '/src/auth.php';
require dirname(__DIR__, 2) . '/src/projects.php';

$user = require_login();
$pdo = db();
$slug = (string) ($_POST['slug'] ?? '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/dashboard/');
}

if (!verify_csrf((string) ($_POST['csrf'] ?? ''))) {
    redirect('/edit/?slug=' . urlencode($slug) . '&delete=stale');
}

$stmt = $pdo->prepare('SELECT id, slug, title, owner_id FROM projects WHERE slug = ?');
$stmt->execute([$slug]);
$project = $stmt->fetch();

if ($project === false) {
    redirect('/dashboard/');
}

$isOwner = (int) $project['owner_id'] === (int) $user['id'];
$isModerator = in_array($user['role'], ['admin', 'moderator'], true);

if (!$isOwner && !$isModerator) {
    http_response_code(403);
    $content = render_template('message', [
        'heading' => 'Not your listing',
        'message' => 'Only the owner or a moderator can delete this listing.',
        'link' => '/dashboard/',
        'linkText' => 'Back to the dashboard',
    ]);
    echo render_site_page('Forbidden — Relay', 'You cannot delete this listing.', '/delete/', $content, true);
    exit;
}

$blockers = project_delete_blockers($pdo, (int) $project['id']);

if ($blockers !== []) {
    http_response_code(409);
    $content = render_template('message', [
        'heading' => 'This listing is still in use',
        'message' => '"' . $project['title'] . '" cannot be deleted while someone is interacting with it. '
            . implode(' ', $blockers)
            . ' Once that has run its course — a decision on each expression of interest, and no revision in review — it can be deleted.',
        'link' => '/edit/?slug=' . urlencode((string) $project['slug']),
        'linkText' => 'Back to the listing',
    ]);
    echo render_site_page('Cannot delete — Relay', 'This listing is still in use.', '/delete/', $content, true);
    exit;
}

try {
    delete_project($pdo, (int) $project['id'], (int) $user['id']);
} catch (Throwable $e) {
    error_log('Relay: could not delete listing ' . $slug . ': ' . $e->getMessage());
    app_log('delete ' . $slug . ': ' . $e->getMessage());

    $content = render_template('message', [
        'heading' => 'That listing could not be deleted',
        'message' => 'Something went wrong and nothing was changed. Try again, or ask the maintainer.',
        'link' => '/edit/?slug=' . urlencode((string) $project['slug']),
        'linkText' => 'Back to the listing',
    ]);

    echo render_site_page('Could not delete — Relay', 'The listing was not deleted.', '/delete/', $content, true);
    exit;
}

redirect('/dashboard/?deleted=1');
