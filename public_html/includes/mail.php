<?php
require_once __DIR__ . '/../config.php';

/**
 * Send a plain-text email via the server's local mail transport.
 * On DirectAdmin/cPanel shared hosting, PHP's mail() is normally wired up
 * to send as the domain's own mail setup already, no SMTP credentials
 * or third-party service needed.
 *
 * In local/dev environments, define MAIL_TEST_MODE => true in config.php
 * to write emails to a log file instead of actually sending.
 */
function send_email(string $to, string $subject, string $body): bool
{
    if (defined('MAIL_TEST_MODE') && MAIL_TEST_MODE) {
        $log = sys_get_temp_dir() . '/c4a_test_mail.log';
        file_put_contents($log, "=== " . date('Y-m-d H:i:s') . " ===\nTO: $to\nSUBJECT: $subject\n\n$body\n\n", FILE_APPEND);
        return true;
    }

    $headers = "From: " . SITE_NAME . " <" . BRAND_CONTACT_EMAIL . ">\r\n"
             . "Reply-To: " . BRAND_CONTACT_EMAIL . "\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n";

    return @mail($to, $subject, $body, $headers);
}
