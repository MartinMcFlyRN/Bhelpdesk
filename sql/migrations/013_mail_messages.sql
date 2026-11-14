-- Email sent and received: duplicates, threading (Message-ID -> ticket),
-- POP3 retention ("keep the last N days") and the log shown in /op/config/email
CREATE TABLE IF NOT EXISTS mail_messages (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    direction          ENUM('in','out') NOT NULL,
    identity_id        INT UNSIGNED NULL,
    pop_uid            VARCHAR(255) NULL,             -- UIDL on the POP3 server (incoming)
    message_id         VARCHAR(255) NULL,             -- without < >
    ticket_id          INT UNSIGNED NULL,
    ticket_message_id  INT UNSIGNED NULL,
    address            VARCHAR(190) NULL,             -- sender (in) / recipient (out)
    subject            VARCHAR(255) NULL,
    status             VARCHAR(16)  NOT NULL,         -- in: ticket, reply, ignored, error / out: sent, error
    note               VARCHAR(255) NULL,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mail_messages_uid (identity_id, pop_uid),
    KEY ix_mail_messages_msgid (message_id),
    KEY ix_mail_messages_ticket (ticket_id),
    KEY ix_mail_messages_address (address, created_at),
    CONSTRAINT fk_mail_messages_identity FOREIGN KEY (identity_id) REFERENCES mail_identities(id) ON DELETE SET NULL,
    CONSTRAINT fk_mail_messages_ticket   FOREIGN KEY (ticket_id)   REFERENCES tickets(id)         ON DELETE SET NULL,
    CONSTRAINT fk_mail_messages_tmessage FOREIGN KEY (ticket_message_id) REFERENCES ticket_messages(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE ticket_messages ADD COLUMN IF NOT EXISTS via_email TINYINT(1) NOT NULL DEFAULT 0 AFTER is_internal;
