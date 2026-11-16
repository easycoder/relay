<?php

/**
 * Local development config. Copy to config.php and fill in the database
 * password; config.php is gitignored.
 *
 * In deployment Relay follows the estate convention instead: a JSON file
 * named after the host, one level above the web root
 * (see relay.eclecity.net.txt.example).
 */

declare(strict_types=1);

return [
    'site' => [
        'url' => 'https://relay.eclecity.net',
    ],

    'db' => [
        'host'    => 'mysql.eclecity.net',
        'port'    => 3306,
        'name'    => 'eclecity_relay',
        'user'    => 'eclecity_relay',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],
];
