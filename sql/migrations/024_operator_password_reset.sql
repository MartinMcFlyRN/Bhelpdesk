-- "Forgot your password?" for operators: single-use links (hash only)
CREATE TABLE IF NOT EXISTS operator_tokens (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operator_id  INT UNSIGNED NOT NULL,
    token_hash   CHAR(64)     NOT NULL,
    expires_at   DATETIME     NOT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_operator_tokens_hash (token_hash),
    KEY ix_operator_tokens_operator (operator_id),
    CONSTRAINT fk_otokens_operator FOREIGN KEY (operator_id) REFERENCES operators(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
