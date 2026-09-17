-- Run this AFTER 003_analytics.sql.
SET NAMES utf8mb4;

-- Cancellation now stops future billing but doesn't cut access early —
-- cancels_at holds the date access actually ends (= current_period_end
-- at the moment of cancellation).
ALTER TABLE companies
    ADD COLUMN cancels_at DATETIME DEFAULT NULL;

-- TOTP-based MFA (RFC 6238) — no third-party service, works with any
-- authenticator app (Google Authenticator, Authy, 1Password, etc.)
ALTER TABLE users
    ADD COLUMN mfa_secret VARCHAR(64) DEFAULT NULL,
    ADD COLUMN mfa_enabled TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN mfa_recovery_codes TEXT DEFAULT NULL;
