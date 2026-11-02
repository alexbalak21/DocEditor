-- Migration 009 — Two-Factor Authentication (email OTP)
-- Stores pending OTP codes issued after successful password check.
-- A row expires after OTP_LIFETIME minutes (default 10).
-- The user is only fully authenticated after entering the correct code.

CREATE TABLE IF NOT EXISTS two_factor_codes (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    code_hash  VARCHAR(255) NOT NULL,        -- bcrypt hash of the 6-digit code
    ip         VARCHAR(45)  NOT NULL,
    attempts   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME     NOT NULL,
    used_at    DATETIME     NULL,
    created_at DATETIME     NOT NULL DEFAULT NOW(),

    INDEX idx_2fa_user    (user_id),
    INDEX idx_2fa_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
