<?php

/**
 * Magic-link sign-in and session helpers.
 *
 * There are no passwords. The browser holds a random session token; the
 * database stores only its SHA-256 hash. Magic-link tokens are single-use,
 * expire after 15 minutes and are stored the same way.
 */

declare(strict_types=1);

function site_url(): string
{
    return rtrim((string) (relay_config()['site']['url'] ?? ''), '/');
}

function mail_from(): string
{
    $config = relay_config();

    return (string) ($config['mail']['from'] ?? $config['mail_from'] ?? 'no-reply@relay.eclecity.net');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

function redirect(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

function client_ip_hash(): string
{
    return hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
}

function cookie_options(int $expires): array
{
    return [
        'expires' => $expires,
        'path' => '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function login_csrf_token(): string
{
    $token = $_COOKIE['relay_login_csrf'] ?? '';

    if (!is_string($token) || strlen($token) !== 64) {
        $token = bin2hex(random_bytes(32));
        setcookie('relay_login_csrf', $token, cookie_options(time() + 3600));
        $_COOKIE['relay_login_csrf'] = $token;
    }

    return $token;
}

function verify_login_csrf(?string $token): bool
{
    $cookie = $_COOKIE['relay_login_csrf'] ?? '';

    return is_string($cookie) && $cookie !== ''
        && is_string($token) && hash_equals($cookie, $token);
}

function csrf_token(): string
{
    $raw = $_COOKIE['relay_session'] ?? '';

    return is_string($raw) && $raw !== '' ? hash_hmac('sha256', 'csrf', $raw) : '';
}

function verify_csrf(?string $token): bool
{
    $expected = csrf_token();

    return $expected !== '' && is_string($token) && hash_equals($expected, $token);
}

function current_user(): ?array
{
    static $user = false;

    if ($user !== false) {
        return $user;
    }

    $raw = $_COOKIE['relay_session'] ?? '';

    if (!is_string($raw) || strlen($raw) !== 64) {
        $user = null;
        return null;
    }

    $stmt = db()->prepare(
        'SELECT u.id, u.email, u.display_name, u.role
         FROM sessions s
         JOIN users u ON u.id = s.user_id
         WHERE s.token_hash = ? AND s.expires_at > UTC_TIMESTAMP()'
    );
    $stmt->execute([hash('sha256', $raw)]);
    $row = $stmt->fetch();

    $user = $row === false ? null : $row;

    return $user;
}

function require_login(): array
{
    $user = current_user();

    if ($user === null) {
        redirect('/login/');
    }

    return $user;
}

function require_role(array $roles): array
{
    $user = require_login();

    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="en-GB"><head><meta charset="utf-8"><meta name="robots" content="noindex">'
            . '<title>Forbidden</title></head><body><h1>Forbidden</h1>'
            . '<p>You do not have access to this page.</p><p><a href="/">Return to Relay</a></p></body></html>';
        exit;
    }

    return $user;
}

function create_session(int $userId): void
{
    $raw = bin2hex(random_bytes(32));
    $pdo = db();

    $pdo->exec('DELETE FROM sessions WHERE expires_at < UTC_TIMESTAMP()');

    $stmt = $pdo->prepare(
        'INSERT INTO sessions (token_hash, user_id, expires_at, last_seen_at, ip_hash, user_agent)
         VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 DAY), UTC_TIMESTAMP(), ?, ?)'
    );
    $stmt->execute([
        hash('sha256', $raw),
        $userId,
        client_ip_hash(),
        substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);

    setcookie('relay_session', $raw, cookie_options(time() + 30 * 24 * 60 * 60));
    $_COOKIE['relay_session'] = $raw;

    setcookie('relay_login_csrf', '', cookie_options(time() - 3600));
    unset($_COOKIE['relay_login_csrf']);
}

function destroy_session(): void
{
    $raw = $_COOKIE['relay_session'] ?? '';

    if (is_string($raw) && $raw !== '') {
        $stmt = db()->prepare('DELETE FROM sessions WHERE token_hash = ?');
        $stmt->execute([hash('sha256', $raw)]);
    }

    setcookie('relay_session', '', cookie_options(time() - 3600));
    unset($_COOKIE['relay_session']);
}

function create_magic_link(string $email): string
{
    $raw = bin2hex(random_bytes(32));
    $pdo = db();

    $pdo->exec('DELETE FROM magic_links WHERE expires_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)');

    $stmt = $pdo->prepare(
        'INSERT INTO magic_links (email, token_hash, purpose, expires_at, requested_ip_hash)
         VALUES (?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 15 MINUTE), ?)'
    );
    $stmt->execute([$email, hash('sha256', $raw), 'login', client_ip_hash()]);

    return $raw;
}

function magic_link_url(string $rawToken): string
{
    return site_url() . '/login/verify/?token=' . urlencode($rawToken);
}

function send_email(string $to, string $subject, string $body): bool
{
    if (!function_exists('mail')) {
        return false;
    }

    $headers = [
        'From: Relay <' . mail_from() . '>',
        'Content-Type: text/plain; charset=UTF-8',
    ];

    return mail($to, $subject, $body, implode("\r\n", $headers), '-f' . mail_from());
}

function send_magic_link(string $email, string $rawToken): bool
{
    $url = magic_link_url($rawToken);
    $subject = 'Sign in to Relay';
    $body = "Use this link to sign in to Relay:\n\n{$url}\n\n"
        . "It expires in 15 minutes. If you did not request it, ignore this email.\n";

    return send_email($email, $subject, $body);
}

function consume_magic_link(string $rawToken): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT id, email FROM magic_links
         WHERE token_hash = ? AND used_at IS NULL AND expires_at > UTC_TIMESTAMP()'
    );
    $stmt->execute([hash('sha256', $rawToken)]);
    $link = $stmt->fetch();

    if ($link === false) {
        return null;
    }

    $update = $pdo->prepare(
        'UPDATE magic_links SET used_at = UTC_TIMESTAMP() WHERE id = ? AND used_at IS NULL'
    );
    $update->execute([$link['id']]);

    return $update->rowCount() === 1 ? $link : null;
}

function user_by_email(string $email): ?array
{
    $stmt = db()->prepare(
        'SELECT id, email, display_name, role, verified_at FROM users WHERE email = ?'
    );
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function create_user(string $email, string $displayName): int
{
    $displayName = trim($displayName);

    if ($displayName === '') {
        throw new InvalidArgumentException('A display name is required.');
    }

    $pdo = db();
    $stmt = $pdo->prepare(
        "INSERT INTO users (email, display_name, role, verified_at)
         VALUES (?, ?, 'user', NULL)
         ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)"
    );
    $stmt->execute([$email, substr($displayName, 0, 100)]);

    return (int) $pdo->lastInsertId();
}

function find_or_create_user(string $email): int
{
    $pdo = db();
    $user = user_by_email($email);

    if ($user !== null) {
        $pdo->prepare(
            'UPDATE users SET verified_at = COALESCE(verified_at, UTC_TIMESTAMP()) WHERE id = ?'
        )->execute([$user['id']]);

        return (int) $user['id'];
    }

    // A link created outside the form (for example by an older CLI command)
    // may not have a name. Derive one so the link still works; the normal form
    // creates the user with the chosen name before sending the link.
    $display = ucfirst((string) (strstr($email, '@', true) ?: $email));
    $id = create_user($email, $display);

    $pdo->prepare(
        'UPDATE users SET verified_at = COALESCE(verified_at, UTC_TIMESTAMP()) WHERE id = ?'
    )->execute([$id]);

    return $id;
}
