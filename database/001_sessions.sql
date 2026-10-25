-- Apply once with a migration account before serving traffic.
-- Additive only: no application table or existing record is changed.
CREATE TABLE IF NOT EXISTS app_sessions (
    session_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    data MEDIUMBLOB NOT NULL,
    expires_at BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (session_key),
    KEY app_sessions_expiry (expires_at)
) ENGINE=InnoDB;
