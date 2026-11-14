-- Tasks created from a ticket keep a reference to it
ALTER TABLE tasks ADD COLUMN IF NOT EXISTS ticket_id INT UNSIGNED NULL AFTER operator_id;
ALTER TABLE tasks ADD INDEX IF NOT EXISTS ix_tasks_ticket (ticket_id);
ALTER TABLE tasks ADD CONSTRAINT fk_tasks_ticket FOREIGN KEY IF NOT EXISTS (ticket_id) REFERENCES tickets(id) ON DELETE SET NULL;
