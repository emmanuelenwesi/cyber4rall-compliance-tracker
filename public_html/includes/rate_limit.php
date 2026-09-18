<?php
require_once __DIR__ . '/db.php';

function client_ip(): string
{
    // REMOTE_ADDR is what the web server actually saw connect; the only
    // value that can't be spoofed by the client. X-Forwarded-For is not
    // trusted here since there's no known, configured trusted proxy in
    // front of this shared-hosting setup.
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Record that an attempt happened, for rate-limiting purposes.
 * Also does light, cheap pruning of old rows so this table never needs
 * a cron job on shared hosting; no cost most of the time, occasional
 * cleanup when it does run.
 */
function record_rate_limit_event(string $action, string $identifier): void
{
    $conn = db();
    $stmt = $conn->prepare('INSERT INTO rate_limit_events (action, identifier) VALUES (?, ?)');
    $stmt->bind_param('ss', $action, $identifier);
    $stmt->execute();
    $stmt->close();

    if (random_int(1, 100) === 1) {
        $conn->query('DELETE FROM rate_limit_events WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
    }
}

function count_recent_events(string $action, string $identifier, int $windowMinutes): int
{
    $conn = db();
    $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM rate_limit_events WHERE action = ? AND identifier = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)');
    $stmt->bind_param('ssi', $action, $identifier, $windowMinutes);
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
    return $count;
}

function is_rate_limited(string $action, string $identifier, int $maxAttempts, int $windowMinutes): bool
{
    return count_recent_events($action, $identifier, $windowMinutes) >= $maxAttempts;
}
