-- Two-factor authentication (TOTP) for operators: mandatory, trusted computers for 30 days
ALTER TABLE operators ADD COLUMN IF NOT EXISTS totp_secret     TEXT NULL AFTER password_hash;   -- encrypted (Crypto)
ALTER TABLE operators ADD COLUMN IF NOT EXISTS totp_enabled_at DATETIME NULL AFTER totp_secret;  -- NULL = to be set up at next login
ALTER TABLE operators ADD COLUMN IF NOT EXISTS totp_last_step  BIGINT NULL AFTER totp_enabled_at; -- last code used (no replay)

CREATE TABLE IF NOT EXISTS operator_recovery_codes (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operator_id  INT UNSIGNED NOT NULL,
    code_hash    CHAR(64) NOT NULL,                    -- sha256 of the normalised code
    used_at      DATETIME NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY ix_recovery_operator (operator_id),
    CONSTRAINT fk_recovery_operator FOREIGN KEY (operator_id) REFERENCES operators(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS operator_trusted_devices (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operator_id   INT UNSIGNED NOT NULL,
    token_hash    CHAR(64) NOT NULL,                   -- sha256 of the cookie value
    user_agent    VARCHAR(255) NULL,
    ip            VARCHAR(45) NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at  DATETIME NULL,
    expires_at    DATETIME NOT NULL,
    UNIQUE KEY uq_trusted_token (token_hash),
    KEY ix_trusted_operator (operator_id),
    CONSTRAINT fk_trusted_operator FOREIGN KEY (operator_id) REFERENCES operators(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
