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
    echo render_site_page('Not found — Relay', 'Listing not found.', '/preview/', $content, true);
    exit;
}

$isOwner = (int) $project['owner_id'] === (int) $user['id'];
$isModerator = in_array($user['role'], ['admin', 'moderator'], true);

if (!$isOwner && !$isModerator) {
    http_response_code(403);
    $content = render_template('message', [
        'heading' => 'Not your listing',
        'message' => 'Only the owner or a moderator can preview this listing.',
        'link' => '/dashboard/',
        'linkText' => 'Back to the dashboard',
    ]);
    echo render_site_page('Forbidden — Relay', 'You cannot preview this listing.', '/preview/', $content, true);
    exit;
}

$relations = project_relations($pdo, (int) $project['id']);
$project['keywords'] = $relations['keywords'];
$project['skills'] = $relations['skills'];
$project['assets'] = $relations['assets'];
$project['obligations'] = $relations['obligations'];
$project['is_draft'] = $project['confirmed_at'] === null;
$project['show_interest'] = $project['review_status'] === 'approved';

$ownerStmt = $pdo->prepare('SELECT display_name FROM users WHERE id = ?');
$ownerStmt->execute([$project['owner_id']]);
$project['owner_name'] = (string) $ownerStmt->fetchColumn();

$categoryStmt = $pdo->prepare('SELECT name FROM categories WHERE slug = ?');
$categoryStmt->execute([$project['category']]);
$project['category_name'] = (string) $categoryStmt->fetchColumn();

$listing = render_template('project', ['project' => $project, 'siteUrl' => '']);

$notice = $project['review_status'] === 'approved'
    ? 'This listing is live. This is the owner preview of the current page.'
    : 'Preview — this listing is not public until it is approved and the catalogue is rebuilt.';

$content = '<p class="notice">' . e($notice) . '</p>'
    . '<p class="actions"><a class="button" href="/edit/?slug=' . e(urlencode($project['slug'])) . '">Edit this listing</a></p>'
    . $listing;

echo render_site_page(
    'Preview: ' . $project['title'] . ' — Relay',
    'Preview of a Relay listing.',
    '/preview/',
    $content,
    true
);
