-- Customer registry fields editable by operators (v0.6). New installs get these from sql/schema.sql.

ALTER TABLE customers ADD COLUMN IF NOT EXISTS company VARCHAR(150) NULL AFTER name;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER language;
CREATE INDEX IF NOT EXISTS ix_customers_name ON customers (name);
