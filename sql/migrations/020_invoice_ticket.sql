-- Invoices created from a ticket keep a reference to it
ALTER TABLE invoices ADD COLUMN IF NOT EXISTS ticket_id INT UNSIGNED NULL AFTER customer_id;
ALTER TABLE invoices ADD INDEX IF NOT EXISTS ix_invoices_ticket (ticket_id);
ALTER TABLE invoices ADD CONSTRAINT fk_invoices_ticket FOREIGN KEY IF NOT EXISTS (ticket_id) REFERENCES tickets(id) ON DELETE SET NULL;
