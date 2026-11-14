-- Customer accounts and expiring ticket links (v0.8).
-- New installs get these from sql/schema.sql.

-- customer accounts
ALTER TABLE customers ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL AFTER notes;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS email_verified_at DATETIME NULL AFTER password_hash;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL AFTER email_verified_at;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS last_login_ip VARCHAR(45) NULL AFTER last_login_at;

-- single-use links to create / reset the password (only the hash is stored)
CREATE TABLE IF NOT EXISTS customer_tokens (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id  INT UNSIGNED NOT NULL,
    token_hash   CHAR(64)     NOT NULL,
    expires_at   DATETIME     NOT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_customer_tokens_hash (token_hash),
    KEY ix_customer_tokens_customer (customer_id),
    CONSTRAINT fk_ctokens_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- direct ticket links, one per (ticket, customer) issue; expiry is computed
-- from the last public message of the ticket (setting customer_link_days)
CREATE TABLE IF NOT EXISTS ticket_links (
    token_hash    CHAR(64)     NOT NULL PRIMARY KEY,
    ticket_id     INT UNSIGNED NOT NULL,
    customer_id   INT UNSIGNED NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at  DATETIME     NULL,
    KEY ix_ticket_links_ticket (ticket_id, customer_id),
    CONSTRAINT fk_tlinks_ticket   FOREIGN KEY (ticket_id)   REFERENCES tickets(id)   ON DELETE CASCADE,
    CONSTRAINT fk_tlinks_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- the old per-ticket token becomes a link of the main customer (stored hashed),
-- then the plain token is cleared. Old URLs /customer/ticket/N?t=TOKEN keep working.
ALTER TABLE tickets MODIFY access_token CHAR(64) NULL;
INSERT IGNORE INTO ticket_links (token_hash, ticket_id, customer_id, created_at)
    SELECT SHA2(access_token, 256), id, customer_id, created_at FROM tickets WHERE access_token IS NOT NULL;
UPDATE tickets SET access_token = NULL WHERE access_token IS NOT NULL;
