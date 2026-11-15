-- Senders whose email is downloaded and logged but ignored
CREATE TABLE IF NOT EXISTS email_blacklist (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pattern      VARCHAR(190) NOT NULL,               -- email address or domain (also covers its subdomains)
    type         ENUM('email','domain') NOT NULL,
    note         VARCHAR(255) NULL,
    hits         INT UNSIGNED NOT NULL DEFAULT 0,     -- messages blocked
    last_hit_at  DATETIME NULL,
    created_by   INT UNSIGNED NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_email_blacklist_pattern (pattern),
    CONSTRAINT fk_blacklist_operator FOREIGN KEY (created_by) REFERENCES operators(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
