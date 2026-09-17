-- Run this AFTER 004_cancel_and_mfa.sql.
SET NAMES utf8mb4;

-- Store only a hash of the reset token, never the token itself — same
-- principle as password hashing. The plaintext token only ever exists
-- in the emailed link.
ALTER TABLE users
    ADD COLUMN reset_token_hash VARCHAR(64) DEFAULT NULL,
    ADD COLUMN reset_token_expires DATETIME DEFAULT NULL;
