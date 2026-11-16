#!/usr/bin/env php
<?php

/**
 * Send a direct test email using the same From and envelope sender as the
 * magic-link email.
 *
 *   php scripts/mail-test.php hello@relay.eclecity.net
 *   php scripts/mail-test.php info@allspeak.ai
 *
 * A true return means the message was handed to the local mail transport, not
 * that it was delivered. Check the recipient's spam folder and the server's
 * mail log if it does not arrive.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
require dirname(__DIR__) . '/src/auth.php';

$to = strtolower(trim((string) ($argv[1] ?? '')));

if (!filter_var($to, FILTER_VALIDATE_EMAIL) || strlen($to) > 190) {
    fwrite(STDERR, "Usage: php scripts/mail-test.php <email>\n");
    exit(1);
}

if (!function_exists('mail')) {
    fwrite(STDERR, "mail() is not available in this PHP.\n");
    exit(1);
}

$subject = 'Relay mail test';
$body = "This is a test from Relay.\n\nIf this arrives, PHP mail() and the sender address work.\n";

echo 'Site: ' . site_url() . PHP_EOL;
echo 'From: ' . mail_from() . PHP_EOL;
echo 'To:   ' . $to . PHP_EOL;

$ok = send_email($to, $subject, $body);

echo $ok ? "mail() returned true (queued)\n" : "mail() returned false (not queued)\n";

exit($ok ? 0 : 1);
