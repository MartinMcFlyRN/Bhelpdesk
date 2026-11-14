-- Source (.eml, gzip) of every downloaded email, viewable by operators
ALTER TABLE mail_messages ADD COLUMN IF NOT EXISTS raw_path VARCHAR(100) NULL AFTER note;
ALTER TABLE mail_messages ADD INDEX IF NOT EXISTS ix_mail_messages_tmessage (ticket_message_id);
