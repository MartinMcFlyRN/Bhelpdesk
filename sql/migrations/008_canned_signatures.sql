-- Canned responses and department signatures (v0.9). New installs get these from sql/schema.sql.

ALTER TABLE departments ADD COLUMN IF NOT EXISTS signature TEXT NULL AFTER active;

CREATE TABLE IF NOT EXISTS canned_responses (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title          VARCHAR(150) NOT NULL,
    body           TEXT         NOT NULL,
    department_id  INT UNSIGNED NULL,
    active         TINYINT(1)   NOT NULL DEFAULT 1,
    updated_by     INT UNSIGNED NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_canned_title (title),
    KEY ix_canned_department (department_id),
    CONSTRAINT fk_canned_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    CONSTRAINT fk_canned_operator   FOREIGN KEY (updated_by)    REFERENCES operators(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
