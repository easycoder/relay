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
