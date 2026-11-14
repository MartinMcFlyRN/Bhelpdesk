-- Email identities: SMTP + POP3 accounts (v0.13). New installs get these from sql/schema.sql.

CREATE TABLE IF NOT EXISTS mail_identities (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    email           VARCHAR(190) NOT NULL,
    department_id   INT UNSIGNED NOT NULL,          -- tickets from downloaded mail go here
    active          TINYINT(1)   NOT NULL DEFAULT 1,
    smtp_host       VARCHAR(253) NOT NULL DEFAULT '',
    smtp_port       SMALLINT UNSIGNED NOT NULL DEFAULT 587,
    smtp_security   ENUM('ssl','starttls','none') NOT NULL DEFAULT 'starttls',
    smtp_user       VARCHAR(190) NOT NULL DEFAULT '',
    smtp_pass       TEXT NULL,                      -- encrypted (Crypto)
    smtp_verify     TINYINT(1)   NOT NULL DEFAULT 1,
    pop_enabled     TINYINT(1)   NOT NULL DEFAULT 0,
    pop_host        VARCHAR(253) NOT NULL DEFAULT '',
    pop_port        SMALLINT UNSIGNED NOT NULL DEFAULT 995,
    pop_security    ENUM('ssl','starttls','none') NOT NULL DEFAULT 'ssl',
    pop_user        VARCHAR(190) NOT NULL DEFAULT '',
    pop_pass        TEXT NULL,                      -- encrypted (Crypto)
    pop_verify      TINYINT(1)   NOT NULL DEFAULT 1,
    pop_keep        ENUM('delete','days','forever') NOT NULL DEFAULT 'days',
    pop_keep_days   SMALLINT UNSIGNED NOT NULL DEFAULT 30,  -- leave on the server the last N days
    pop_checked_at  DATETIME NULL,
    pop_status      VARCHAR(255) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mail_identities_email (email),
    KEY ix_mail_identities_department (department_id),
    CONSTRAINT fk_mail_identities_department FOREIGN KEY (department_id) REFERENCES departments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- identity used by each department to send its mail (may differ from the receiving one)
ALTER TABLE departments ADD COLUMN IF NOT EXISTS send_identity_id INT UNSIGNED NULL AFTER signature;
ALTER TABLE departments ADD CONSTRAINT fk_departments_send_identity FOREIGN KEY IF NOT EXISTS (send_identity_id) REFERENCES mail_identities(id) ON DELETE SET NULL;
