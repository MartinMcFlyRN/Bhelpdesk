-- Attachments (v0.3). New installs get this table from sql/schema.sql.

CREATE TABLE IF NOT EXISTS attachments (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id      INT UNSIGNED NOT NULL,
    message_id     INT UNSIGNED NOT NULL,
    original_name  VARCHAR(190) NOT NULL,
    stored_name    VARCHAR(64)  NOT NULL,
    mime           VARCHAR(100) NOT NULL,
    size           INT UNSIGNED NOT NULL,
    sha256         CHAR(64)     NOT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_attachments_stored (stored_name),
    KEY ix_attachments_message (message_id),
    KEY ix_attachments_ticket (ticket_id),
    CONSTRAINT fk_attachments_ticket  FOREIGN KEY (ticket_id)  REFERENCES tickets(id)         ON DELETE CASCADE,
    CONSTRAINT fk_attachments_message FOREIGN KEY (message_id) REFERENCES ticket_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
