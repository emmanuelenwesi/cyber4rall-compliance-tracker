-- Run this AFTER schema.sql and 002_billing.sql.
-- A minimal, self-hosted, cookie-free pageview log — deliberately not a
-- third-party analytics cookie, since that would sit awkwardly on a
-- privacy-compliance product without consent.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS pageviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    path VARCHAR(255) NOT NULL,
    company_id INT DEFAULT NULL,
    referrer VARCHAR(255) DEFAULT NULL,
    visited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_path_date (path, visited_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
