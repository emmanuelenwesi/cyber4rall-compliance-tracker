-- Run this AFTER 005_password_reset.sql.
SET NAMES utf8mb4;

-- Generic rate-limit event log. One row per attempt (login, MFA code,
-- password reset request). Kept intentionally simple — no cron job
-- needed, old rows are pruned automatically on insert (see rate_limit.php).
CREATE TABLE IF NOT EXISTS rate_limit_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(30) NOT NULL,
    identifier VARCHAR(191) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_action_identifier_time (action, identifier, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
