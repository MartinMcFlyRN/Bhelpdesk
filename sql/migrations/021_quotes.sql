-- Billing: quotes for the customers (optionally created from a ticket)
CREATE TABLE IF NOT EXISTS quotes (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id  INT UNSIGNED NOT NULL,
    ticket_id    INT UNSIGNED NULL,                            -- quote created from a ticket
    description  TEXT          NOT NULL,
    amount       DECIMAL(12,2) NOT NULL,
    created_by   INT UNSIGNED NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY ix_quotes_customer (customer_id),
    KEY ix_quotes_ticket (ticket_id),
    CONSTRAINT fk_quotes_customer   FOREIGN KEY (customer_id) REFERENCES customers(id),
    CONSTRAINT fk_quotes_ticket     FOREIGN KEY (ticket_id)   REFERENCES tickets(id)   ON DELETE SET NULL,
    CONSTRAINT fk_quotes_created_by FOREIGN KEY (created_by)  REFERENCES operators(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
