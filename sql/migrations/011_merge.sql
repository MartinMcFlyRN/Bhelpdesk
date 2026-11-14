-- Ticket merge (v0.12). New installs get this from sql/schema.sql.
-- A merged ticket keeps only merged_into: its number/links redirect to that ticket.
-- Deleting a ticket also deletes the tickets merged into it.

ALTER TABLE tickets ADD COLUMN IF NOT EXISTS merged_into INT UNSIGNED NULL AFTER closed_at;
ALTER TABLE tickets ADD CONSTRAINT fk_tickets_merged FOREIGN KEY IF NOT EXISTS (merged_into) REFERENCES tickets(id) ON DELETE CASCADE;
