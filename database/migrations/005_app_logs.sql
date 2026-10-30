-- App logs table — mirrors the file log for web-based viewing and querying.
-- The file log in storage/logs/ is the primary source of truth.

CREATE TABLE IF NOT EXISTS app_logs (
    id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level     ENUM('DEBUG','INFO','WARNING','ERROR') NOT NULL DEFAULT 'INFO',
    event     VARCHAR(100)  NOT NULL,
    ip        VARCHAR(45)   NOT NULL DEFAULT '',
    user_id   INT UNSIGNED  NULL,
    context   JSON          NULL,
    logged_at DATETIME      NOT NULL DEFAULT NOW(),

    INDEX idx_logs_level      (level),
    INDEX idx_logs_event      (event),
    INDEX idx_logs_ip         (ip),
    INDEX idx_logs_user       (user_id),
    INDEX idx_logs_logged_at  (logged_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
