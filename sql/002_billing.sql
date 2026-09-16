-- Run this AFTER schema.sql, once, to add Paystack billing support.
SET NAMES utf8mb4;

ALTER TABLE companies
    ADD COLUMN paystack_customer_code VARCHAR(64) DEFAULT NULL,
    ADD COLUMN paystack_subscription_code VARCHAR(64) DEFAULT NULL,
    ADD COLUMN paystack_email_token VARCHAR(128) DEFAULT NULL,
    ADD COLUMN subscription_status ENUM('trial','active','past_due','cancelled') NOT NULL DEFAULT 'trial',
    ADD COLUMN trial_ends_at DATETIME DEFAULT NULL,
    ADD COLUMN current_period_end DATETIME DEFAULT NULL;

-- Backfill existing companies (e.g. your own test/admin company) with a trial window
-- so nobody gets locked out immediately after this migration runs.
UPDATE companies SET trial_ends_at = DATE_ADD(created_at, INTERVAL 14 DAY) WHERE trial_ends_at IS NULL;

CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    reference VARCHAR(100) NOT NULL UNIQUE,
    amount_kobo BIGINT NOT NULL,
    status VARCHAR(30) NOT NULL,
    paid_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
