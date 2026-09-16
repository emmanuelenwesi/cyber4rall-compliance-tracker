<?php
require_once __DIR__ . '/db.php';

/**
 * Log a pageview against the current request. Fails silently (analytics
 * should never break the page) — e.g. before the 003_analytics.sql
 * migration has been run.
 */
function log_pageview(?int $companyId = null): void
{
    try {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $referrer = isset($_SERVER['HTTP_REFERER']) ? substr($_SERVER['HTTP_REFERER'], 0, 255) : null;

        $conn = db();
        $stmt = $conn->prepare('INSERT INTO pageviews (path, company_id, referrer) VALUES (?, ?, ?)');
        $stmt->bind_param('sis', $path, $companyId, $referrer);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        // Analytics table may not exist yet, or DB hiccup — never fatal.
    }
}
