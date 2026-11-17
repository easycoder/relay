<?php

/**
 * Shared review decision used by the CLI and the web review queue.
 */

declare(strict_types=1);

require_once __DIR__ . '/projects.php';

function review_project(
    PDO $pdo,
    int $projectId,
    string $status,
    string $note,
    ?int $actorUserId,
    string $actorLabel
): void {
    if (!in_array($status, ['approved', 'rejected'], true)) {
        throw new InvalidArgumentException('Review status must be approved or rejected.');
    }

    $note = substr(trim($note), 0, 500);

    $update = $pdo->prepare(
        "UPDATE projects
         SET review_status = ?,
             review_note = ?,
             published_at = CASE
                 WHEN ? = 'approved' THEN COALESCE(published_at, UTC_TIMESTAMP())
                 ELSE published_at
             END,
             expires_at = CASE
                 WHEN ? = 'approved' THEN COALESCE(expires_at, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 6 MONTH))
                 ELSE expires_at
             END
         WHERE id = ?"
    );
    $update->execute([
        $status,
        $status === 'rejected' && $note !== '' ? $note : null,
        $status,
        $status,
        $projectId,
    ]);

    $detail = 'Reviewed by ' . $actorLabel . ($note !== '' ? ': ' . $note : '');

    $audit = $pdo->prepare(
        "INSERT INTO audit_log (actor_user_id, action, entity_type, entity_id, detail, created_at)
         VALUES (?, ?, 'project', ?, ?, UTC_TIMESTAMP())"
    );
    $audit->execute([$actorUserId, 'project.' . $status, $projectId, $detail]);
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

            $pdo->prepare(
                'UPDATE projects SET expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 6 MONTH) WHERE id = ?'
            )->execute([$revision['project_id']]);
        }

        $pdo->prepare(
            'UPDATE project_revisions
             SET review_status = ?, reviewed_at = UTC_TIMESTAMP(), reviewed_by = ?, note = ?
             WHERE id = ?'
        )->execute([$status, $actorUserId, $note !== '' ? $note : null, $revisionId]);

        $detail = 'Revision reviewed by ' . $actorLabel . ($note !== '' ? ': ' . $note : '');
        $audit = $pdo->prepare(
            "INSERT INTO audit_log (actor_user_id, action, entity_type, entity_id, detail, created_at)
             VALUES (?, ?, 'project_revision', ?, ?, UTC_TIMESTAMP())"
        );
        $audit->execute([$actorUserId, 'revision.' . $status, $revisionId, $detail]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}
