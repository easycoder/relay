<?php

/**
 * Shared review decisions used by the CLI and the web review queue.
 *
 * Approval can come from a person or from the community threshold. Rejection
 * always comes from a person and blocks automatic approval for that listing;
 * otherwise a rejection would be one endorsement away from being undone. A
 * veto is a rejection of a listing the community already approved, recorded
 * separately so it does not count as a submission strike against the owner.
 */

declare(strict_types=1);

require_once __DIR__ . '/projects.php';
require_once __DIR__ . '/auth.php';

function review_project(
    PDO $pdo,
    int $projectId,
    string $status,
    string $note,
    ?int $actorUserId,
    string $actorLabel,
    string $source = 'admin',
    string $detail = ''
): void {
    if (!in_array($status, ['approved', 'rejected'], true)) {
        throw new InvalidArgumentException('Review status must be approved or rejected.');
    }

    if (!in_array($source, ['admin', 'community'], true)) {
        $source = 'admin';
    }

    $note = substr(trim($note), 0, 500);

    $ownerStmt = $pdo->prepare('SELECT owner_id FROM projects WHERE id = ?');
    $ownerStmt->execute([$projectId]);
    $ownerId = $ownerStmt->fetchColumn();

    if ($ownerId === false) {
        throw new RuntimeException('That listing no longer exists.');
    }

    $approved = $status === 'approved';

    $update = $pdo->prepare(
        "UPDATE projects
         SET review_status = ?,
             review_note = ?,
             approval_source = ?,
             auto_approval_blocked = ?,
             review_diff = NULL,
             edit_review_status = 'accepted',
             published_at = CASE
                 WHEN ? = 'approved' THEN COALESCE(published_at, UTC_TIMESTAMP())
                 ELSE published_at
             END,
             expires_at = CASE
                 WHEN ? = 'approved' THEN DATE_ADD(UTC_TIMESTAMP(), INTERVAL " . EXPIRY_INTERVAL . ")
                 ELSE expires_at
             END
         WHERE id = ?"
    );
    $update->execute([
        $status,
        $approved ? null : ($note !== '' ? $note : null),
        $approved ? $source : null,
        $approved ? 0 : 1,
        $status,
        $status,
        $projectId,
    ]);

    $detail = $detail !== ''
        ? $detail
        : 'Reviewed by ' . $actorLabel . ($note !== '' ? ': ' . $note : '');

    $audit = $pdo->prepare(
        "INSERT INTO audit_log (actor_user_id, subject_user_id, action, entity_type, entity_id, detail, created_at)
         VALUES (?, ?, ?, 'project', ?, ?, UTC_TIMESTAMP())"
    );
    $audit->execute([$actorUserId, (int) $ownerId, 'project.' . $status, $projectId, $detail]);
}

/**
 * Reject a listing the community has already approved.
 *
 * A veto needs a reason, because it is the one decision that overrides other
 * people's endorsements. It does not count towards the owner's rejection
 * strikes: the owner has not resubmitted anything, and a matter of editorial
 * judgement should not pause their account.
 */
function veto_project(
    PDO $pdo,
    int $projectId,
    string $note,
    ?int $actorUserId,
    string $actorLabel
): void {
    $note = substr(trim($note), 0, 500);

    if ($note === '') {
        throw new InvalidArgumentException('A veto needs a reason.');
    }

    $ownerStmt = $pdo->prepare('SELECT owner_id FROM projects WHERE id = ?');
    $ownerStmt->execute([$projectId]);
    $ownerId = $ownerStmt->fetchColumn();

    if ($ownerId === false) {
        throw new RuntimeException('That listing no longer exists.');
    }

    $update = $pdo->prepare(
        "UPDATE projects
         SET review_status = 'rejected',
             review_note = ?,
             approval_source = NULL,
             auto_approval_blocked = 1
         WHERE id = ? AND review_status = 'approved'"
    );
    $update->execute([$note, $projectId]);

    if ($update->rowCount() === 0) {
        throw new RuntimeException('That listing is not approved, so it cannot be vetoed.');
    }

    $audit = $pdo->prepare(
        "INSERT INTO audit_log (actor_user_id, subject_user_id, action, entity_type, entity_id, detail, created_at)
         VALUES (?, ?, 'project.vetoed', 'project', ?, ?, UTC_TIMESTAMP())"
    );
    $audit->execute([
        $actorUserId,
        (int) $ownerId,
        $projectId,
        'Vetoed by ' . $actorLabel . ': ' . $note,
    ]);
}

/**
 * Apply a live edit immediately.
 *
 * The caller owns the transaction. A pending revision from the old
 * review-every-edit flow would otherwise overlap this edit, so it is
 * superseded rather than applied.
 *
 * @param array<string, mixed> $data
 */
function record_live_edit(
    PDO $pdo,
    int $projectId,
    int $ownerId,
    int $actorUserId,
    array $data,
    string $diffJson
): void {
    $stmt = $pdo->prepare('SELECT review_status FROM projects WHERE id = ? FOR UPDATE');
    $stmt->execute([$projectId]);

    if ($stmt->fetchColumn() !== 'approved') {
        throw new RuntimeException('That listing is not live, so the edit cannot go straight in.');
    }

    apply_project_data($pdo, $projectId, $data);

    $pdo->prepare(
        "UPDATE projects
         SET edited_at = UTC_TIMESTAMP(),
             edit_review_status = 'pending',
             review_diff = ?,
             review_note = NULL,
             approval_source = NULL
         WHERE id = ?"
    )->execute([$diffJson, $projectId]);

    $pdo->prepare(
        "UPDATE project_revisions
         SET review_status = 'rejected',
             reviewed_at = UTC_TIMESTAMP(),
             note = 'Superseded by a direct edit'
         WHERE project_id = ? AND review_status = 'pending'"
    )->execute([$projectId]);

    $audit = $pdo->prepare(
        "INSERT INTO audit_log (actor_user_id, subject_user_id, action, entity_type, entity_id, detail, created_at)
         VALUES (?, ?, 'project.edited', 'project', ?, ?, UTC_TIMESTAMP())"
    );
    $audit->execute([
        $actorUserId,
        $ownerId,
        $projectId,
        'Edited via /edit/; live immediately.',
    ]);
}

/**
 * Let an edit stay: the marker disappears and it leaves the re-review list.
 */
function accept_edit(PDO $pdo, int $projectId, ?int $actorUserId, string $actorLabel): void
{
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            "SELECT owner_id FROM projects
             WHERE id = ? AND review_status = 'approved' AND edit_review_status = 'pending'
             FOR UPDATE"
        );
        $stmt->execute([$projectId]);
        $ownerId = $stmt->fetchColumn();

        if ($ownerId === false) {
            throw new RuntimeException('That edit is not awaiting review.');
        }

        $pdo->prepare(
            "UPDATE projects
             SET edit_review_status = 'accepted', review_diff = NULL, approval_source = 'admin'
             WHERE id = ?"
        )->execute([$projectId]);

        $audit = $pdo->prepare(
            "INSERT INTO audit_log (actor_user_id, subject_user_id, action, entity_type, entity_id, detail, created_at)
             VALUES (?, ?, 'project.edit_accepted', 'project', ?, ?, UTC_TIMESTAMP())"
        );
        $audit->execute([
            $actorUserId,
            (int) $ownerId,
            $projectId,
            'Edit accepted by ' . $actorLabel,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

/**
 * Take a live listing down pending a correction and email the owner.
 *
 * The listing leaves the catalogue on the next build. A correction goes back
 * through initial review, and `auto_approval_blocked` stops endorsements from
 * putting it back on their own.
 */
function take_down_project(
    PDO $pdo,
    int $projectId,
    string $note,
    ?int $actorUserId,
    string $actorLabel
): void {
    $note = substr(trim($note), 0, 500);

    if ($note === '') {
        throw new InvalidArgumentException('A take-down needs a reason.');
    }

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            "SELECT p.id, p.title, p.slug, p.owner_id, p.review_status,
                    u.email AS owner_email, u.display_name AS owner_name
             FROM projects p
             JOIN users u ON u.id = p.owner_id
             WHERE p.id = ?
             FOR UPDATE"
        );
        $stmt->execute([$projectId]);
        $project = $stmt->fetch();

        if ($project === false || $project['review_status'] !== 'approved') {
            throw new RuntimeException('That listing is not approved, so it cannot be taken down.');
        }

        $pdo->prepare(
            "UPDATE projects
             SET review_status = 'taken_down',
                 review_note = ?,
                 approval_source = NULL,
                 auto_approval_blocked = 1,
                 edit_review_status = 'taken_down'
             WHERE id = ?"
        )->execute([$note, $projectId]);

        $audit = $pdo->prepare(
            "INSERT INTO audit_log (actor_user_id, subject_user_id, action, entity_type, entity_id, detail, created_at)
             VALUES (?, ?, 'project.taken_down', 'project', ?, ?, UTC_TIMESTAMP())"
        );
        $audit->execute([
            $actorUserId,
            (int) $project['owner_id'],
            $projectId,
            'Taken down by ' . $actorLabel . ': ' . $note,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    $body = 'Hello ' . $project['owner_name'] . ',' . PHP_EOL . PHP_EOL
        . '"' . $project['title'] . '" has been taken down from Relay.' . PHP_EOL . PHP_EOL
        . 'Reason: ' . $note . PHP_EOL . PHP_EOL
        . 'Correct the listing and resubmit it from your dashboard. A person will review it before it is published again.' . PHP_EOL . PHP_EOL
        . site_url() . '/dashboard/' . PHP_EOL;

    if (!send_email((string) $project['owner_email'], 'Your Relay listing was taken down', $body)) {
        app_log('take_down: could not email ' . $project['owner_email']);
    }
}

function review_revision(
    PDO $pdo,
    int $revisionId,
    string $status,
    string $note,
    ?int $actorUserId,
    string $actorLabel
): void {
    if (!in_array($status, ['approved', 'rejected'], true)) {
        throw new InvalidArgumentException('Review status must be approved or rejected.');
    }

    $note = substr(trim($note), 0, 500);
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            "SELECT * FROM project_revisions WHERE id = ? AND review_status = 'pending' FOR UPDATE"
        );
        $stmt->execute([$revisionId]);
        $revision = $stmt->fetch();

        if ($revision === false) {
            throw new RuntimeException('That revision has already been reviewed or no longer exists.');
        }

        $ownerStmt = $pdo->prepare('SELECT owner_id FROM projects WHERE id = ?');
        $ownerStmt->execute([$revision['project_id']]);
        $ownerId = (int) $ownerStmt->fetchColumn();

        if ($status === 'approved') {
            $data = revision_data($revision);

            if ($data === []) {
                throw new RuntimeException('The revision payload is empty.');
            }

            apply_project_data(
                $pdo,
                (int) $revision['project_id'],
                $data,
                (string) $revision['created_at']
            );

            // The content is now live under a person's decision, so a revision
            // approval also clears a community approval's provenance.
            $pdo->prepare(
                "UPDATE projects
                 SET expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL " . EXPIRY_INTERVAL . "),
                     approval_source = 'admin',
                     auto_approval_blocked = 0,
                     review_diff = NULL,
                     edit_review_status = 'accepted'
                 WHERE id = ?"
            )->execute([$revision['project_id']]);
        }

        $pdo->prepare(
            'UPDATE project_revisions
             SET review_status = ?, reviewed_at = UTC_TIMESTAMP(), reviewed_by = ?, note = ?
             WHERE id = ?'
        )->execute([$status, $actorUserId, $note !== '' ? $note : null, $revisionId]);

        $detail = 'Revision reviewed by ' . $actorLabel . ($note !== '' ? ': ' . $note : '');
        $audit = $pdo->prepare(
            "INSERT INTO audit_log (actor_user_id, subject_user_id, action, entity_type, entity_id, detail, created_at)
             VALUES (?, ?, ?, 'project_revision', ?, ?, UTC_TIMESTAMP())"
        );
        $audit->execute([$actorUserId, $ownerId, 'revision.' . $status, $revisionId, $detail]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}
