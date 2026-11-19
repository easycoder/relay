<?php

/**
 * Reasoned endorsements on a pending listing.
 *
 * A pending listing can be approved by a threshold of endorsements unless a
 * reviewer has rejected it. An endorsement is not a click: the reason is kept,
 * shown to readers of the waiting page and to the reviewer, and one account
 * may endorse a listing once. The threshold and the account-age gate are
 * settings, so the operator can raise the bar without a deploy.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/review.php';

/**
 * Read a community setting from either the nested or the flat config shape.
 */
function like_setting(string $key, int $default): int
{
    $config = relay_config();
    $value = $config['community'][$key] ?? $config[$key] ?? $default;

    return max(0, (int) $value);
}

function like_threshold(): int
{
    return max(1, like_setting('like_threshold', 5));
}

function like_min_account_age_days(): int
{
    return like_setting('like_min_account_age_days', 7);
}

function like_ip_daily_limit(): int
{
    return max(1, like_setting('like_ip_daily_limit', 10));
}

function like_reason_min_length(): int
{
    return 20;
}

function like_reason_max_length(): int
{
    return 500;
}

function project_endorsement_count(PDO $pdo, int $projectId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM project_likes WHERE project_id = ?');
    $stmt->execute([$projectId]);

    return (int) $stmt->fetchColumn();
}

/**
 * Endorsements in the order they were made, with the name of the endorser.
 *
 * @return array<int, array<string, mixed>>
 */
function project_endorsements(PDO $pdo, int $projectId): array
{
    $stmt = $pdo->prepare(
        'SELECT l.reason, l.created_at, u.display_name
         FROM project_likes l
         JOIN users u ON u.id = l.user_id
         WHERE l.project_id = ?
         ORDER BY l.created_at ASC, l.id ASC'
    );
    $stmt->execute([$projectId]);

    return $stmt->fetchAll();
}

function user_endorsement(PDO $pdo, int $projectId, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM project_likes WHERE project_id = ? AND user_id = ? LIMIT 1'
    );
    $stmt->execute([$projectId, $userId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * Whether this account may endorse this listing now.
 *
 * @param array<string, mixed> $project
 * @param array<string, mixed>|null $user
 * @return array{allowed: bool, reason: string}
 */
function endorsement_eligibility(PDO $pdo, array $project, ?array $user): array
{
    if ($user === null) {
        return ['allowed' => false, 'reason' => 'signin'];
    }

    if (($project['review_status'] ?? '') !== 'pending') {
        return ['allowed' => false, 'reason' => 'closed'];
    }

    if (!empty($project['expires_at']) && strtotime((string) $project['expires_at'] . ' UTC') <= time()) {
        return ['allowed' => false, 'reason' => 'expired'];
    }

    if ((int) $project['owner_id'] === (int) $user['id']) {
        return ['allowed' => false, 'reason' => 'own'];
    }

    if (user_endorsement($pdo, (int) $project['id'], (int) $user['id']) !== null) {
        return ['allowed' => false, 'reason' => 'already'];
    }

    $minAge = like_min_account_age_days();

    if ($minAge > 0) {
        $stmt = $pdo->prepare('SELECT created_at FROM users WHERE id = ?');
        $stmt->execute([(int) $user['id']]);
        $createdAt = $stmt->fetchColumn();

        if (is_string($createdAt) && $createdAt !== '') {
            $age = time() - (int) strtotime($createdAt . ' UTC');

            if ($age < $minAge * 86400) {
                return ['allowed' => false, 'reason' => 'new'];
            }
        }
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM project_likes
         WHERE ip_hash = ? AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)'
    );
    $stmt->execute([client_ip_hash()]);

    if ((int) $stmt->fetchColumn() >= like_ip_daily_limit()) {
        return ['allowed' => false, 'reason' => 'limit'];
    }

    return ['allowed' => true, 'reason' => ''];
}

/**
 * Record an endorsement and approve the listing if this one reaches the
 * threshold.
 *
 * The project row is locked for the whole operation, so two simultaneous
 * endorsements cannot both write an approval and two audit rows. The caller
 * owns no transaction: this function starts and finishes one.
 *
 * @return array{recorded: bool, reason: string, count: int, approved: bool}
 */
function record_endorsement(
    PDO $pdo,
    int $projectId,
    int $userId,
    string $reason,
    string $ipHash
): array {
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'SELECT id, owner_id, review_status, auto_approval_blocked, expires_at
             FROM projects WHERE id = ? FOR UPDATE'
        );
        $stmt->execute([$projectId]);
        $project = $stmt->fetch();

        if ($project === false || $project['review_status'] !== 'pending') {
            $pdo->rollBack();

            return ['recorded' => false, 'reason' => 'closed', 'count' => 0, 'approved' => false];
        }

        if (!empty($project['expires_at']) && strtotime((string) $project['expires_at'] . ' UTC') <= time()) {
            $pdo->rollBack();

            return ['recorded' => false, 'reason' => 'expired', 'count' => 0, 'approved' => false];
        }

        $existing = $pdo->prepare(
            'SELECT 1 FROM project_likes WHERE project_id = ? AND user_id = ?'
        );
        $existing->execute([$projectId, $userId]);

        if ($existing->fetchColumn() !== false) {
            $pdo->rollBack();

            return ['recorded' => false, 'reason' => 'already', 'count' => 0, 'approved' => false];
        }

        try {
            $pdo->prepare(
                'INSERT INTO project_likes (project_id, user_id, reason, ip_hash, created_at)
                 VALUES (?, ?, ?, ?, UTC_TIMESTAMP())'
            )->execute([$projectId, $userId, $reason, $ipHash]);
        } catch (PDOException $e) {
            // A simultaneous request from the same account lost the race on
            // the unique key. Treat it as already endorsed.
            if ((string) $e->getCode() === '23000') {
                $pdo->rollBack();

                return ['recorded' => false, 'reason' => 'already', 'count' => 0, 'approved' => false];
            }

            throw $e;
        }

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM project_likes WHERE project_id = ?');
        $countStmt->execute([$projectId]);
        $count = (int) $countStmt->fetchColumn();

        $approved = false;

        if ($count >= like_threshold() && empty($project['auto_approval_blocked'])) {
            review_project(
                $pdo,
                $projectId,
                'approved',
                '',
                null,
                'community threshold',
                'community',
                'Community approval after ' . $count . ' endorsement' . ($count === 1 ? '' : 's') . '.'
            );

            $approved = true;
        }

        $pdo->commit();

        return [
            'recorded' => true,
            'reason' => '',
            'count' => $count,
            'approved' => $approved,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

/**
 * Remove every endorsement from a listing.
 *
 * An edit changes the content people endorsed, so the next version starts
 * with a clean count. A rejection also leaves the existing rows in place
 * until the owner edits; the listing is not public meanwhile.
 */
function clear_project_likes(PDO $pdo, int $projectId): void
{
    $pdo->prepare('DELETE FROM project_likes WHERE project_id = ?')->execute([$projectId]);
}
