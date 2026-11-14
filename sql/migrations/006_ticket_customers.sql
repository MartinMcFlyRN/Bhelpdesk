-- Additional customers on a ticket + author of customer messages (v0.7).
-- New installs get these from sql/schema.sql.

CREATE TABLE IF NOT EXISTS ticket_customers (
    ticket_id    INT UNSIGNED NOT NULL,
    customer_id  INT UNSIGNED NOT NULL,
    added_by     INT UNSIGNED NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ticket_id, customer_id),
    KEY ix_ticket_customers_customer (customer_id),
    CONSTRAINT fk_tc_ticket   FOREIGN KEY (ticket_id)   REFERENCES tickets(id)   ON DELETE CASCADE,
    CONSTRAINT fk_tc_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    CONSTRAINT fk_tc_operator FOREIGN KEY (added_by)    REFERENCES operators(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE ticket_messages ADD COLUMN IF NOT EXISTS customer_id INT UNSIGNED NULL AFTER operator_id;
ALTER TABLE ticket_messages ADD CONSTRAINT fk_messages_customer FOREIGN KEY IF NOT EXISTS (customer_id) REFERENCES customers(id) ON DELETE SET NULL;

-- existing customer messages were all written by the ticket's main customer
UPDATE ticket_messages m JOIN tickets t ON t.id = m.ticket_id
   SET m.customer_id = t.customer_id
 WHERE m.author_type = 'customer' AND m.customer_id IS NULL;
