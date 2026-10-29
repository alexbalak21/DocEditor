-- Remembered devices: one row per browser that stays signed in ("Keep me signed in").
-- The cookie holds "selector.validator"; only a SHA-256 hash of the validator is stored, so a
-- database leak cannot be used to sign in. The validator is replaced (rotated) regularly.

CREATE TABLE IF NOT EXISTS auth_devices (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED NOT NULL,
    selector         CHAR(24)     NOT NULL,
    token_hash       CHAR(64)     NOT NULL,
    prev_hash        CHAR(64)     NULL,
    prev_valid_until DATETIME     NULL,
    auth_version     INT UNSIGNED NOT NULL DEFAULT 1,
    browser          VARCHAR(40)  NOT NULL DEFAULT '',
    os               VARCHAR(40)  NOT NULL DEFAULT '',
    user_agent       VARCHAR(255) NOT NULL DEFAULT '',
    ip_created       VARCHAR(45)  NOT NULL DEFAULT '',
    ip_last          VARCHAR(45)  NOT NULL DEFAULT '',
    created_at       DATETIME     NOT NULL DEFAULT NOW(),
    last_used_at     DATETIME     NOT NULL DEFAULT NOW(),
    rotated_at       DATETIME     NULL,
    expires_at       DATETIME     NOT NULL,
    hard_expires_at  DATETIME     NULL,
    UNIQUE KEY uq_device_selector (selector),
    INDEX idx_device_user (user_id),
    INDEX idx_device_expires (expires_at),
    CONSTRAINT fk_device_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
