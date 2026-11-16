<?php

/**
 * Expression-of-interest relay.
 *
 * An applicant's email is shown to the owner only after the owner accepts;
 * accepting also sends an introduction to both parties.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function interest_status_label(string $status): string
{
    $labels = [
        'new' => 'Awaiting response',
        'accepted' => 'Accepted',
        'declined' => 'Declined',
        'withdrawn' => 'Withdrawn',
    ];

    return $labels[$status] ?? ucfirst($status);
}

function find_interest(PDO $pdo, int $projectId, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM interests WHERE project_id = ? AND user_id = ? LIMIT 1'
    );
    $stmt->execute([$projectId, $userId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function validate_interest_submission(array $input): array
{
    $input = array_map(static fn ($value) => is_array($value) ? '' : $value, $input);

    $data = [
        'experience' => trim((string) ($input['experience'] ?? '')),
        'availability' => trim((string) ($input['availability'] ?? '')),
        'first_30_days' => trim((string) ($input['first_30_days'] ?? '')),
        'wants' => trim((string) ($input['wants'] ?? '')),
        'message' => trim((string) ($input['message'] ?? '')),
    ];

    $errors = [];

    if ($data['experience'] === '') {
        $errors['experience'] = 'Say what relevant experience you have.';
    }

    if ($data['availability'] === '') {
        $errors['availability'] = 'Say how much time you can give.';
    }

    if ($data['first_30_days'] === '') {
        $errors['first_30_days'] = 'Say what you would do in the first 30 days.';
    }

    if ($data['wants'] === '') {
        $errors['wants'] = 'Say what you want from the project.';
    }

    $limits = [
        'experience' => 5000,
        'availability' => 120,
        'first_30_days' => 5000,
        'wants' => 5000,
        'message' => 2000,
    ];

    foreach ($limits as $field => $max) {
        if (strlen($data[$field]) > $max) {
            $errors[$field] = "Keep this under {$max} characters.";
        }
    }

    if (empty($input['confirm'])) {
        $errors['confirm'] = 'Please confirm that your email may be shared if the owner accepts.';
    }

    return [$data, $errors];
}

function create_interest(PDO $pdo, int $projectId, int $userId, array $data): int
{
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO interests
                (project_id, user_id, experience, availability, first_30_days, wants, message, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'new', UTC_TIMESTAMP())"
        );
        $stmt->execute([
            $projectId,
            $userId,
            $data['experience'],
            $data['availability'],
            $data['first_30_days'],
            $data['wants'],
            $data['message'],
        ]);

        $interestId = (int) $pdo->lastInsertId();

        $audit = $pdo->prepare(
            "INSERT INTO audit_log (actor_user_id, action, entity_type, entity_id, detail, created_at)
             VALUES (?, 'interest.submitted', 'interest', ?, ?, UTC_TIMESTAMP())"
        );
        $audit->execute([$userId, $interestId, 'Expression of interest submitted']);

        $pdo->commit();

        return $interestId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function interest_context(PDO $pdo, int $interestId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT i.*, p.slug, p.title AS project_title, p.owner_id,
                owner.display_name AS owner_name, owner.email AS owner_email,
                applicant.display_name AS applicant_name, applicant.email AS applicant_email
         FROM interests i
         JOIN projects p ON p.id = i.project_id
         JOIN users owner ON owner.id = p.owner_id
         JOIN users applicant ON applicant.id = i.user_id
         WHERE i.id = ?'
    );
    $stmt->execute([$interestId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function notify_owner_of_interest(PDO $pdo, int $projectId): void
{
    $stmt = $pdo->prepare(
        'SELECT p.title, owner.email AS owner_email
         FROM projects p
         JOIN users owner ON owner.id = p.owner_id
         WHERE p.id = ?'
    );
    $stmt->execute([$projectId]);
    $project = $stmt->fetch();

    if ($project === false) {
        return;
    }

    $subject = 'Relay: new expression of interest in ' . $project['title'];
    $body = "A new expression of interest has been submitted for {$project['title']}.\n\n"
        . 'Sign in at ' . site_url() . "/interests/ to read it and decide.\n";

    if (!send_email((string) $project['owner_email'], $subject, $body)) {
        error_log('Relay: could not send the owner interest notification.');
    }
}

function decide_interest(
    PDO $pdo,
    int $interestId,
    int $actorUserId,
    string $status,
    string $note
): void {
    if (!in_array($status, ['accepted', 'declined'], true)) {
        throw new InvalidArgumentException('Interest status must be accepted or declined.');
    }

    $note = substr(trim($note), 0, 500);
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            "SELECT id FROM interests WHERE id = ? AND status = 'new' FOR UPDATE"
        );
        $stmt->execute([$interestId]);

        if ($stmt->fetchColumn() === false) {
            throw new RuntimeException('That expression has already been decided.');
        }

        $context = interest_context($pdo, $interestId);

        if ($context === null) {
            throw new RuntimeException('The expression of interest no longer exists.');
        }

        if ((int) $context['owner_id'] !== $actorUserId) {
            throw new RuntimeException('Only the owner can decide on this expression.');
        }

        $pdo->prepare(
            'UPDATE interests
             SET status = ?, decided_at = UTC_TIMESTAMP(), decided_by = ?, decision_note = ?
             WHERE id = ?'
        )->execute([$status, $actorUserId, $note !== '' ? $note : null, $interestId]);

        $audit = $pdo->prepare(
            "INSERT INTO audit_log (actor_user_id, action, entity_type, entity_id, detail, created_at)
             VALUES (?, ?, 'interest', ?, ?, UTC_TIMESTAMP())"
        );
        $audit->execute([
            $actorUserId,
            'interest.' . $status,
            $interestId,
            'Owner decision via /interests/' . ($note !== '' ? ': ' . $note : ''),
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    if ($status !== 'accepted') {
        return;
    }

    $ownerSubject = 'Relay: you accepted ' . $context['applicant_name'];
    $ownerBody = "You accepted {$context['applicant_name']}'s expression of interest in "
        . "{$context['project_title']}.\n\n"
        . "You can reply directly to {$context['applicant_name']} at {$context['applicant_email']}.\n\n"
        . "Their first-30-days note:\n{$context['first_30_days']}\n";

    $applicantSubject = 'Relay: your interest in ' . $context['project_title'] . ' was accepted';
    $applicantBody = "{$context['owner_name']} accepted your expression of interest in "
        . "{$context['project_title']}.\n\n"
        . "You can reach them at {$context['owner_email']}.\n";

    if (!send_email((string) $context['owner_email'], $ownerSubject, $ownerBody)) {
        error_log('Relay: could not send the acceptance email to the owner.');
    }

    if (!send_email((string) $context['applicant_email'], $applicantSubject, $applicantBody)) {
        error_log('Relay: could not send the acceptance email to the applicant.');
    }
}
