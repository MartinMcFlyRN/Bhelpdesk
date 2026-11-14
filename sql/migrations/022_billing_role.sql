-- "billing" operators: only the Billing section; the only ones who can mark invoices issued
ALTER TABLE operators MODIFY role ENUM('admin','agent','billing') NOT NULL DEFAULT 'agent';
