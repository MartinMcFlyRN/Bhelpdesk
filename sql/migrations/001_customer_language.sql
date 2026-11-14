-- Only for databases created before multi-language support (v0.1).
-- New installs already have this column from sql/schema.sql.
ALTER TABLE customers ADD COLUMN IF NOT EXISTS language VARCHAR(10) NOT NULL DEFAULT 'en' AFTER email;
