-- Migration 007 — User feedback notes
-- Users write notes (text + pasted screenshots) from any page.
-- Images stored as base64 data URIs in a JSON array.

CREATE TABLE IF NOT EXISTS notes (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    url        VARCHAR(500) NOT NULL DEFAULT '',
    title      VARCHAR(255) NOT NULL DEFAULT '',
    body       TEXT         NOT NULL,
    images     LONGTEXT     NOT NULL,  -- JSON array of base64 data URIs
    created_at DATETIME     NOT NULL DEFAULT NOW(),

    INDEX idx_notes_user    (user_id),
    INDEX idx_notes_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
