<?php

/**
 * Magic-link sign-in and session helpers.
 *
 * There are no passwords. The browser holds a random session token; the
 * database stores only its SHA-256 hash. Magic-link tokens are single-use,
 * expire after 15 minutes and are stored the same way.
 *
 * Sessions slide: while one is in use it is extended to thirty days from the
 * last renewal, so a working session never expires mid-use and a dormant one
 * still lapses. The browser may also keep other accounts it has signed in
 * with, each as its own session in a second httpOnly cookie, so switching
 * between them costs a click rather than an email.
 */

declare(strict_types=1);

/** How long a session lasts from its last renewal. */
const RELAY_SESSION_DAYS = 30;

/** Renew a session once it has less than this long left. */
const RELAY_SESSION_RENEW_WITHIN = 15 * 24 * 60 * 60;

/** How many other accounts a browser may remember. */
const RELAY_MAX_REMEMBERED_ACCOUNTS = 6;

/** How long the remembered-account cookie itself lasts. */
const RELAY_ACCOUNT_COOKIE_DAYS = 180;

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

/**
 * Whether a string is shaped like a session token: 64 hexadecimal characters.
 *
 * Written as a pattern rather than ctype_xdigit() so the site does not depend
 * on the ctype extension being enabled.
 */
function is_session_token(mixed $value): bool
{
    return is_string($value) && preg_match('/\A[0-9a-f]{64}\z/', $value) === 1;
}

/**
 * The raw session token the browser is presenting, or null if it has none.
 */
function active_session_token(): ?string
{
    $raw = $_COOKIE['relay_session'] ?? '';

    if (!is_session_token($raw)) {
        return null;
    }

    return $raw;
}

/**
 * Look a session up by its raw token, with the account it belongs to.
 *
 * @return array<string, mixed>|null
 */
function session_by_token(string $raw): ?array
{
    if (!is_session_token($raw)) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT s.token_hash, s.user_id, s.expires_at, u.email, u.display_name, u.role
         FROM sessions s
         JOIN users u ON u.id = s.user_id
         WHERE s.token_hash = ? AND s.expires_at > UTC_TIMESTAMP()'
    );
    $stmt->execute([hash('sha256', $raw)]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * The session the browser is currently presenting.
 *
 * @return array<string, mixed>|null
 */
function active_session(): ?array
{
    $token = active_session_token();

    return $token === null ? null : session_by_token($token);
}

/**
 * Push a session's expiry out to a full term once it is within the renewal
 * window, and reissue the cookie so the browser keeps it for as long.
 *
 * Without this a session died on a fixed date even while in daily use, and the
 * only way back in was another email link.
 *
 * @param array<string, mixed> $session
 */
function renew_session(array $session): void
{
    $expires = strtotime((string) $session['expires_at'] . ' UTC');

    if ($expires === false || $expires - time() > RELAY_SESSION_RENEW_WITHIN) {
        return;
    }

    $raw = active_session_token();

    if ($raw === null) {
        return;
    }

    $seconds = RELAY_SESSION_DAYS * 24 * 60 * 60;

    db()->prepare(
        'UPDATE sessions
         SET expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL ' . RELAY_SESSION_DAYS . ' DAY),
             last_seen_at = UTC_TIMESTAMP()
         WHERE token_hash = ?'
    )->execute([(string) $session['token_hash']]);

    setcookie('relay_session', $raw, cookie_options(time() + $seconds));
}

function current_user(): ?array
{
    static $user = false;

    if ($user !== false) {
        return $user;
    }

    $session = active_session();

    if ($session === null) {
        $user = null;
        return null;
    }

    renew_session($session);

    $user = [
        'id' => (int) $session['user_id'],
        'email' => (string) $session['email'],
        'display_name' => (string) $session['display_name'],
        'role' => (string) $session['role'],
    ];

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

/**
 * Turn a session row into a remembered-account entry.
 *
 * The entry carries the raw token so the browser can present it later. It
 * lives in an httpOnly cookie, so page scripts cannot read it.
 *
 * @param array<string, mixed> $session
 * @return array<string, mixed>
 */
function session_entry(array $session, string $raw): array
{
    return [
        'token' => $raw,
        'expires' => (int) (strtotime((string) $session['expires_at'] . ' UTC') ?: 0),
        'name' => (string) $session['display_name'],
        'email' => (string) $session['email'],
        'role' => (string) $session['role'],
    ];
}

/**
 * The accounts this browser remembers, newest first.
 *
 * @return array<int, array<string, mixed>>
 */
function remembered_accounts(): array
{
    $raw = $_COOKIE['relay_accounts'] ?? '';

    if (!is_string($raw) || $raw === '') {
        return [];
    }

    $padded = str_pad(strtr($raw, '-_', '+/'), (int) (ceil(strlen($raw) / 4) * 4), '=');
    $json = base64_decode($padded, true);

    if ($json === false) {
        return [];
    }

    $data = json_decode($json, true);

    if (!is_array($data)) {
        return [];
    }

    $accounts = [];

    foreach ($data as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $token = $entry['token'] ?? '';

        if (!is_session_token($token)) {
            continue;
        }

        $accounts[] = [
            'token' => $token,
            'expires' => (int) ($entry['expires'] ?? 0),
            'name' => substr((string) ($entry['name'] ?? ''), 0, 100),
            'email' => substr((string) ($entry['email'] ?? ''), 0, 190),
            'role' => substr((string) ($entry['role'] ?? ''), 0, 16),
        ];
    }

    return $accounts;
}

/**
 * Write the remembered list back to its cookie, one entry per account.
 *
 * The list holds the accounts that are *not* active; the active one is the
 * `relay_session` cookie. Keeping exactly one copy of each token means a
 * switch has a single place to rotate.
 *
 * @param array<int, array<string, mixed>> $accounts
 */
function save_remembered_accounts(array $accounts): void
{
    $seen = [];
    $kept = [];

    foreach ($accounts as $account) {
        $email = strtolower((string) ($account['email'] ?? ''));
        $key = $email !== '' ? $email : (string) ($account['token'] ?? '');

        if ($key === '' || isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $kept[] = $account;

        if (count($kept) >= RELAY_MAX_REMEMBERED_ACCOUNTS) {
            break;
        }
    }

    if ($kept === []) {
        setcookie('relay_accounts', '', cookie_options(time() - 3600));
        unset($_COOKIE['relay_accounts']);
        return;
    }

    $json = json_encode(array_values($kept), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $value = rtrim(strtr(base64_encode($json === false ? '[]' : $json), '+/', '-_'), '=');

    setcookie(
        'relay_accounts',
        $value,
        cookie_options(time() + RELAY_ACCOUNT_COOKIE_DAYS * 24 * 60 * 60)
    );

    $_COOKIE['relay_accounts'] = $value;
}

/**
 * Remembered entries whose session is still alive, with the data the account
 * list shows. Dead entries are left out; callers that want them pruned save
 * the result back.
 *
 * @return array<int, array<string, mixed>>
 */
function live_remembered_accounts(): array
{
    $accounts = remembered_accounts();

    if ($accounts === []) {
        return [];
    }

    $hashes = [];
    $byHash = [];

    foreach ($accounts as $account) {
        $hash = hash('sha256', (string) $account['token']);
        $hashes[] = $hash;
        $byHash[$hash] = $account;
    }

    $placeholders = implode(', ', array_fill(0, count($hashes), '?'));
    $stmt = db()->prepare(
        "SELECT s.token_hash, s.expires_at, u.email, u.display_name, u.role
         FROM sessions s
         JOIN users u ON u.id = s.user_id
         WHERE s.token_hash IN ({$placeholders}) AND s.expires_at > UTC_TIMESTAMP()"
    );
    $stmt->execute($hashes);

    $live = [];

    foreach ($stmt->fetchAll() as $row) {
        $entry = $byHash[(string) $row['token_hash']] ?? null;

        if ($entry === null) {
            continue;
        }

        $live[(string) $row['token_hash']] = session_entry($row, (string) $entry['token']);
    }

    $ordered = [];

    foreach ($accounts as $account) {
        $row = $live[hash('sha256', (string) $account['token'])] ?? null;

        if ($row !== null) {
            $ordered[] = $row;
        }
    }

    return $ordered;
}

/**
 * Move the session the browser is presenting into the remembered list.
 *
 * Called just before a new sign-in replaces it, so signing in as a second
 * account does not throw the first one away.
 */
function remember_active_session(): void
{
    $raw = active_session_token();
    $session = $raw === null ? null : session_by_token($raw);

    if ($raw === null || $session === null) {
        return;
    }

    $accounts = remembered_accounts();

    array_unshift($accounts, session_entry($session, $raw));

    save_remembered_accounts($accounts);
}

/**
 * Make a remembered account the active one, without an email link.
 *
 * The account's token is rotated as it becomes active and its expiry is
 * pushed out to a full term. The account being left behind goes back into the
 * remembered list, so the switch can be reversed the same way.
 *
 * @return array<string, mixed>|null The now-active account, or null if that
 *                                   account's session has lapsed.
 */
function switch_account(string $token): ?array
{
    $accounts = remembered_accounts();
    $target = null;
    $remaining = [];

    foreach ($accounts as $account) {
        if ($target === null && hash_equals((string) $account['token'], $token)) {
            $target = $account;
            continue;
        }

        $remaining[] = $account;
    }

    if ($target === null) {
        return null;
    }

    if ($token === active_session_token()) {
        return null;
    }

    $session = session_by_token((string) $target['token']);

    if ($session === null) {
        // The session lapsed while it sat in the list: forget it.
        save_remembered_accounts($remaining);

        return null;
    }

    $current = active_session();

    if ($current !== null && active_session_token() !== null) {
        array_unshift($remaining, session_entry($current, (string) active_session_token()));
    }

    $fresh = bin2hex(random_bytes(32));

    db()->prepare(
        'UPDATE sessions
         SET token_hash = ?,
             expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL ' . RELAY_SESSION_DAYS . ' DAY),
             last_seen_at = UTC_TIMESTAMP()
         WHERE token_hash = ?'
    )->execute([hash('sha256', $fresh), (string) $session['token_hash']]);

    save_remembered_accounts($remaining);

    setcookie('relay_session', $fresh, cookie_options(time() + RELAY_SESSION_DAYS * 24 * 60 * 60));
    $_COOKIE['relay_session'] = $fresh;

    return [
        'id' => (int) $session['user_id'],
        'email' => (string) $session['email'],
        'display_name' => (string) $session['display_name'],
        'role' => (string) $session['role'],
    ];
}

/**
 * Drop one remembered account: its session row goes, and so does its entry.
 */
function forget_account(string $token): void
{
    $remaining = [];
    $found = false;

    foreach (remembered_accounts() as $account) {
        if (hash_equals((string) $account['token'], $token)) {
            $found = true;
            continue;
        }

        $remaining[] = $account;
    }

    if (!$found) {
        return;
    }

    db()->prepare('DELETE FROM sessions WHERE token_hash = ?')->execute([hash('sha256', $token)]);
    save_remembered_accounts($remaining);
}

/**
 * End everything this browser holds: every remembered session and the active
 * one.
 */
function forget_browser(): void
{
    foreach (remembered_accounts() as $account) {
        db()->prepare('DELETE FROM sessions WHERE token_hash = ?')
            ->execute([hash('sha256', (string) $account['token'])]);
    }

    save_remembered_accounts([]);
    destroy_session();
}

function create_session(int $userId): void
{
    $raw = bin2hex(random_bytes(32));
    $pdo = db();

    // Keep the account this browser is already signed in as, so a second
    // sign-in does not throw the first away.
    remember_active_session();

    $pdo->exec('DELETE FROM sessions WHERE expires_at < UTC_TIMESTAMP()');

    $stmt = $pdo->prepare(
        'INSERT INTO sessions (token_hash, user_id, expires_at, last_seen_at, ip_hash, user_agent)
         VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ' . RELAY_SESSION_DAYS . ' DAY), UTC_TIMESTAMP(), ?, ?)'
    );
    $stmt->execute([
        hash('sha256', $raw),
        $userId,
        client_ip_hash(),
        substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);

    setcookie('relay_session', $raw, cookie_options(time() + RELAY_SESSION_DAYS * 24 * 60 * 60));
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

/**
 * Write a single-use sign-in link.
 *
 * The display name travels with the link rather than going straight into a
 * user row: the account is created when this link is used, so a request for an
 * address nobody can read never leaves anything behind.
 */
function create_magic_link(string $email, string $displayName = ''): string
{
    $raw = bin2hex(random_bytes(32));
    $pdo = db();

    $displayName = substr(trim($displayName), 0, 100);

    $pdo->exec('DELETE FROM magic_links WHERE expires_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)');

    $stmt = $pdo->prepare(
        'INSERT INTO magic_links (email, display_name, token_hash, purpose, expires_at, requested_ip_hash)
         VALUES (?, ?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 15 MINUTE), ?)'
    );
    $stmt->execute([
        $email,
        $displayName !== '' ? $displayName : null,
        hash('sha256', $raw),
        'login',
        client_ip_hash(),
    ]);

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
        'SELECT id, email, display_name FROM magic_links
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

/**
 * Resolve the account behind a sign-in link, creating it on first use.
 *
 * This is the only place an account is created from the web: the row appears
 * when a link is consumed, so the address must have been read for it to exist.
 */
function find_or_create_user(string $email, string $displayName = ''): int
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
    // may carry no name. Derive one so the link still works.
    $display = trim($displayName);

    if ($display === '') {
        $display = ucfirst((string) (strstr($email, '@', true) ?: $email));
    }

    $id = create_user($email, $display);

    $pdo->prepare(
        'UPDATE users SET verified_at = COALESCE(verified_at, UTC_TIMESTAMP()) WHERE id = ?'
    )->execute([$id]);

    return $id;
}
