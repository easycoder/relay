<?php

/**
 * Reconfirm a published listing from the expiry reminder.
 *
 * The link proves the owner can still read the address on the listing, so no
 * session is needed. Using it pushes the expiry out six months; the row in
 * project_expiry_reminders stays as the record that the reminder was sent.
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';
require dirname(__DIR__, 2) . '/src/html.php';
require dirname(__DIR__, 2) . '/src/auth.php';
require dirname(__DIR__, 2) . '/src/projects.php';

$token = (string) ($_GET['token'] ?? '');
$heading = 'That link is not valid';
$message = 'It has expired or has already been used. Sign in and edit the listing instead.';

if (strlen($token) === 64) {
    $link = consume_magic_link($token);

    if ($link !== null && ($link['purpose'] ?? '') === 'reconfirm' && $link['project_id'] !== null) {
        $projectId = (int) $link['project_id'];

        $stmt = db()->prepare('SELECT id, slug, title, owner_id, review_status FROM projects WHERE id = ?');
        $stmt->execute([$projectId]);
        $project = $stmt->fetch();

        if ($project === false) {
            $heading = 'That listing has gone';
            $message = 'It no longer exists, so there is nothing to reconfirm.';
        } elseif ($project['review_status'] !== 'approved') {
            $heading = 'That listing is not published';
            $message = 'Only a published listing needs reconfirming. You can see where it stands on the dashboard.';
        } else {
            try {
                reconfirm_project(db(), $projectId, (int) $project['owner_id']);

                $heading = 'Thank you — the listing stays published';
                $message = '"' . $project['title'] . '" is confirmed for another six months. '
                    . 'You will be reminded again before it expires.';
            } catch (RuntimeException $e) {
                $heading = 'That listing could not be reconfirmed';
                $message = $e->getMessage();
            }
        }
    }
}

$content = render_template('message', [
    'heading' => $heading,
    'message' => $message,
    'link' => '/dashboard/',
    'linkText' => 'Go to the dashboard',
]);

echo render_site_page(
    $heading . ' — Relay',
    'Confirm a Relay listing is still current.',
    '/reconfirm/',
    $content,
    true
);
