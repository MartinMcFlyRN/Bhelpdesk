-- Settings edited from /op/config/general (v0.4). New installs get this table from sql/schema.sql.

CREATE TABLE IF NOT EXISTS settings (
    name        VARCHAR(64)  NOT NULL PRIMARY KEY,
    value       TEXT         NOT NULL,
    updated_by  INT UNSIGNED NULL,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_settings_operator FOREIGN KEY (updated_by) REFERENCES operators(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
