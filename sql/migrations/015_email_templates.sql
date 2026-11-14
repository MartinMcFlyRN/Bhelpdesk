-- Automatic emails edited in /op/config/templates (missing rows = built-in defaults)
CREATE TABLE IF NOT EXISTS email_templates (
    code        VARCHAR(40)  NOT NULL PRIMARY KEY,
    subject     VARCHAR(255) NOT NULL,
    body        MEDIUMTEXT   NOT NULL,          -- sanitized HTML with {placeholders}
    updated_by  INT UNSIGNED NULL,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_email_templates_operator FOREIGN KEY (updated_by) REFERENCES operators(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
