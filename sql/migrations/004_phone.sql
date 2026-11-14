-- Optional contact phone on tickets (v0.5). New installs get these columns from sql/schema.sql.

ALTER TABLE customers ADD COLUMN IF NOT EXISTS phone VARCHAR(30) NULL AFTER email;
ALTER TABLE tickets   ADD COLUMN IF NOT EXISTS phone VARCHAR(30) NULL AFTER subject;
