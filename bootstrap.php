<?php

/**
 * Locate and load the site configuration.
 *
 * Relay follows the estate convention: a JSON file named after the host,
 * one level above the web root. On DreamHost that is:
 *
 *   /home/eclecity/relay.eclecity.net.txt
 *   /home/eclecity/relay.eclecity.net/      <- web root (this repository)
 *
 * Local development can keep using config.php in the repository root, or a
 * JSON file named config.json. RELAY_CONFIG overrides both.
 *
 * The database password must never be served by a credentials.php endpoint
 * the way the public MQTT credentials are: this file is read server-side only.
 */

declare(strict_types=1);

function relay_config_path(): string
{
    $candidates = [];

    $env = getenv('RELAY_CONFIG');
    if (is_string($env) && $env !== '') {
        $candidates[] = $env;
    }

    // Deployment: <parent>/<directory-name>.txt, where the directory is the
    // host (for example relay.eclecity.net/ -> relay.eclecity.net.txt).
    $site = basename(__DIR__);
    if ($site !== '' && $site !== '.' && $site !== DIRECTORY_SEPARATOR) {
        $candidates[] = dirname(__DIR__) . '/' . $site . '.txt';
    }

    // Local development.
    $candidates[] = __DIR__ . '/config.php';
    $candidates[] = __DIR__ . '/config.json';

    // Older suggestions; kept so existing deployments keep working.
    $candidates[] = dirname(__DIR__) . '/relay-config/config.php';
    $candidates[] = dirname(__DIR__) . '/config.php';

    foreach ($candidates as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    throw new RuntimeException(
        "No config file found. Set RELAY_CONFIG or place a config file in one of:\n  " .
        implode("\n  ", $candidates)
    );
}

function relay_config(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $path = relay_config_path();

    if (str_ends_with(strtolower($path), '.php')) {
        $raw = require $path;
    } else {
        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException("Could not read config file: {$path}");
        }

        $raw = json_decode($json, true);
        if (!is_array($raw)) {
            throw new RuntimeException(
                "Config file is not valid JSON: {$path} (" . json_last_error_msg() . ')'
            );
        }
    }

    if (!is_array($raw)) {
        throw new RuntimeException('Config file must return an array or contain a JSON object.');
    }

    // Accept the estate's flat JSON keys as well as the nested shape used by
    // config.example.php. Extra keys are preserved for later use.
    if (!isset($raw['db']) || !is_array($raw['db'])) {
        $raw['site'] = [
            'url' => (string) ($raw['site_url'] ?? ''),
        ];
        $raw['db'] = [
            'host'    => (string) ($raw['db_host'] ?? ''),
            'port'    => (int) ($raw['db_port'] ?? 3306),
            'name'    => (string) ($raw['db_name'] ?? ''),
            'user'    => (string) ($raw['db_user'] ?? ''),
            'pass'    => (string) ($raw['db_pass'] ?? ''),
            'charset' => (string) ($raw['db_charset'] ?? 'utf8mb4'),
        ];
    }

    $config = $raw;

    return $config;
}

/**
 * Return a shared PDO connection using the loaded config.
 *
 * The connection is created once per process; the build script and any web
 * entry point can call this without passing the config around.
 */
/**
 * Append a line to storage/error.log.
 *
 * Apache's error log on this host does not carry PHP errors, and a bare 500
 * tells nobody anything, so failures are written here as well as to error_log().
 * The .log extension is denied by .htaccess, so the file is not web-readable.
 *
 * Never throws: logging must not be the reason a request fails.
 */
function app_log(string $message): void
{
    static $file = null;

    if ($file === null) {
        $dir = __DIR__ . '/storage';

        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            // The repository may not be writable from the PHP worker; the
            // system temporary directory always is, so keep logging there.
            $file = rtrim(sys_get_temp_dir(), '/') . '/relay-error.log';
        } else {
            $file = $dir . '/error.log';
        }
    }

    $where = ($_SERVER['REQUEST_METHOD'] ?? '-') . ' ' . ($_SERVER['REQUEST_URI'] ?? '-');
    $line = gmdate('Y-m-d H:i:s') . ' UTC ' . $where . ' ' . $message;

    $written = @file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);

    // If the file cannot be written (permissions, missing directory), fall back
    // to the host's log rather than losing the message entirely.
    if ($written === false) {
        error_log('Relay: ' . $line . ' (could not write ' . $file . ')');
    }
}

// Record any fatal that no try/catch caught. Without this a blank 500 leaves
// nothing behind at all: Apache's log has no PHP errors on this host, and the
// per-page handlers only cover the paths they wrap.
register_shutdown_function(static function (): void {
    $error = error_get_last();

    if ($error === null) {
        return;
    }

    $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];

    if (!in_array((int) ($error['type'] ?? 0), $fatal, true)) {
        return;
    }

    app_log('fatal: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
});

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = relay_config();
    $db = $config['db'] ?? [];

    foreach (['host', 'name', 'user', 'pass'] as $key) {
        if (!array_key_exists($key, $db)) {
            throw new RuntimeException("Config is missing db.{$key}.");
        }
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $db['host'],
        $db['port'] ?? 3306,
        $db['name'],
        $db['charset'] ?? 'utf8mb4'
    );

    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Keep stored timestamps and NOW() comparisons in UTC, matching the
    // schema and content SQL, regardless of the server's session time zone.
    $pdo->exec("SET time_zone = '+00:00'");

    return $pdo;
}
