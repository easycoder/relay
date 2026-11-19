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

    'mail' => [
        'from' => 'no-reply@relay.eclecity.net',

        // Where operator notifications go: a listing or revision entering the
        // review queue. Subjects all start "Relay:". Leave empty to send none.
        'admin' => 'hello@relay.eclecity.net',
    ],

    'community' => [
        // Reasoned likes needed to approve a pending listing without a
        // reviewer. A reviewer can still veto any approval, and a rejection by
        // a reviewer blocks automatic approval for that listing.
        'like_threshold' => 5,

        // Accounts younger than this cannot like. This is the cheap defence
        // against one person creating several accounts to approve their own
        // listing; it costs time rather than a click.
        'like_min_account_age_days' => 7,

        // Likes one IP address may leave in a rolling 24 hours.
        'like_ip_daily_limit' => 10,

        // How long an edit to a live listing stays in the re-review list, and
        // how long the public "Edited" marker shows. If nobody acts, the edit
        // is accepted after this many days.
        'edit_review_days' => 14,
    ],
];
