-- "My tickets" of the customer area: tickets of a customer by last update, paginated in SQL
ALTER TABLE tickets ADD INDEX IF NOT EXISTS ix_tickets_customer_updated (customer_id, updated_at);
