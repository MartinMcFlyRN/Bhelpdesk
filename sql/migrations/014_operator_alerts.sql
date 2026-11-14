-- Email alerts to operators: preferences and language of the emails
ALTER TABLE operators ADD COLUMN IF NOT EXISTS language     VARCHAR(10) NULL AFTER email;
ALTER TABLE operators ADD COLUMN IF NOT EXISTS alert_new    TINYINT(1) NOT NULL DEFAULT 1 AFTER active;
ALTER TABLE operators ADD COLUMN IF NOT EXISTS alert_reply  TINYINT(1) NOT NULL DEFAULT 1 AFTER alert_new;
ALTER TABLE operators ADD COLUMN IF NOT EXISTS alert_assign TINYINT(1) NOT NULL DEFAULT 1 AFTER alert_reply;
