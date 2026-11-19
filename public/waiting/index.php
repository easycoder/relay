<?php

/**
 * The public view of a pending listing.
 *
 * A pending listing is not in the catalogue, but the pending view at /find/
 * makes the queue visible. This page is the listing itself: the brief, the
 * likes and the reason form. It is noindex, and it is not the canonical
 * page — that appears at /projects/<slug>/ only after approval and a build.
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';
require dirname(__DIR__, 2) . '/src/html.php';
require dirname(__DIR__, 2) . '/src/auth.php';
require dirname(__DIR__, 2) . '/src/projects.php';
require dirname(__DIR__, 2) . '/src/likes.php';

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
        'link' => '/find/',
        'linkText' => 'Find a project',
    ]);
    echo render_site_page('Not found — Relay', 'Listing not found.', '/waiting/', $content, true);
    exit;
}

$expired = !empty($project['expires_at'])
    && strtotime((string) $project['expires_at'] . ' UTC') <= time();

if ($expired) {
    http_response_code(404);
    $content = render_template('message', [
        'heading' => 'This listing is not pending approval',
        'message' => 'It has expired. Only current listings waiting for approval appear here.',
        'link' => '/find/?view=pending',
        'linkText' => 'See what is waiting',
    ]);
    echo render_site_page('Not available — Relay', 'A listing that is not pending approval.', '/waiting/', $content, true);
    exit;
}

if ($project['review_status'] === 'approved') {
    $justApproved = ((string) ($_GET['like'] ?? '') === 'approved');

    $content = render_template('message', [
        'heading' => $justApproved ? 'That like approved it' : 'This listing has been approved',
        'message' => $justApproved
            ? 'The listing reached the like threshold and has been approved. It will appear in the catalogue after the next build.'
            : 'This listing has been approved and is no longer in the waiting list. It will be in the catalogue after the next build.',
        'link' => '/find/',
        'linkText' => 'Find a project',
    ]);

    echo render_site_page(
        'Approved — Relay',
        'A Relay listing awaiting publication.',
        '/waiting/',
        $content,
        true
    );
    exit;
}

if ($project['review_status'] !== 'pending'
    || in_array((string) $project['status'], ['draft', 'archived'], true)
) {
    http_response_code(404);
    $content = render_template('message', [
        'heading' => 'This listing is not pending approval',
        'message' => 'It may have been withdrawn or rejected. Only listings waiting for approval appear here.',
        'link' => '/find/?view=pending',
        'linkText' => 'See what is waiting',
    ]);
    echo render_site_page('Not available — Relay', 'A listing that is not pending approval.', '/waiting/', $content, true);
    exit;
}

$relations = project_relations($pdo, (int) $project['id']);
$project['keywords'] = $relations['keywords'];
$project['skills'] = $relations['skills'];
$project['assets'] = $relations['assets'];
$project['obligations'] = $relations['obligations'];
$project['is_draft'] = false;
$project['show_interest'] = false;

$ownerStmt = $pdo->prepare('SELECT display_name FROM users WHERE id = ?');
$ownerStmt->execute([$project['owner_id']]);
$project['owner_name'] = (string) $ownerStmt->fetchColumn();

$categoryStmt = $pdo->prepare('SELECT name FROM categories WHERE slug = ?');
$categoryStmt->execute([$project['category']]);
$project['category_name'] = (string) $categoryStmt->fetchColumn();

$endorsements = project_endorsements($pdo, (int) $project['id']);
$count = count($endorsements);
$project['like_count'] = $count;
$threshold = like_threshold();

$user = current_user();
$eligibility = endorsement_eligibility($pdo, $project, $user);
$myEndorsement = $user !== null
    ? user_endorsement($pdo, (int) $project['id'], (int) $user['id'])
    : null;

$likeStatus = (string) ($_GET['like'] ?? '');
$likeMessages = [
    'recorded' => 'Your like has been recorded.',
    'already' => 'You have already liked this listing.',
    'short' => 'Give a reason of at least ' . like_reason_min_length() . ' characters.',
    'own' => 'You cannot like your own listing.',
    'new' => 'Your account is too new to like listings.',
    'limit' => 'Too many likes from this connection today. Try again tomorrow.',
    'expired' => 'This listing has expired and is no longer pending approval.',
    'closed' => 'This listing is not open for likes.',
    'error' => 'That like could not be saved. Please try again.',
];
$likeMessage = $likeMessages[$likeStatus] ?? '';

$reportAddress = admin_address() !== '' ? admin_address() : mail_from();
$reportUrl = 'mailto:' . $reportAddress
    . '?subject=' . rawurlencode('Report a Relay listing: ' . $project['title'])
    . '&body=' . rawurlencode(
        'Listing: ' . site_url() . '/waiting/?slug=' . rawurlencode((string) $project['slug']) . "\n\n"
        . "What is wrong:\n"
    );

$content = render_template('waiting', [
    'project' => $project,
    'count' => $count,
    'threshold' => $threshold,
    'endorsements' => $endorsements,
    'eligibility' => $eligibility,
    'user' => $user,
    'myEndorsement' => $myEndorsement,
    'likeMessage' => $likeMessage,
    'csrf' => csrf_token(),
    'reportUrl' => $reportUrl,
]) . render_template('project', [
    'project' => $project,
    'siteUrl' => site_url(),
    'backUrl' => '/find/?view=pending',
    'backText' => 'Waiting for approval',
]);

echo render_site_page(
    'Waiting for review: ' . $project['title'] . ' — Relay',
    'A listing pending approval on Relay.',
    '/waiting/',
    $content,
    true
);
