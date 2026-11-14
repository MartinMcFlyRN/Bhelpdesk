-- WYSIWYG editor and operator attachments (v0.11). New installs get these from sql/schema.sql.

-- message format: 'text' (customers, older messages) or 'html' (operator editor, sanitized)
ALTER TABLE ticket_messages ADD COLUMN IF NOT EXISTS body_format ENUM('text','html') NOT NULL DEFAULT 'text' AFTER body;

-- images inserted with the editor, served publicly from /customer/media/<file_name>
CREATE TABLE IF NOT EXISTS media (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_name    VARCHAR(64)  NOT NULL,
    mime         VARCHAR(50)  NOT NULL,
    size         INT UNSIGNED NOT NULL,
    width        INT UNSIGNED NOT NULL DEFAULT 0,
    height       INT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_by  INT UNSIGNED NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_media_file (file_name),
    CONSTRAINT fk_media_operator FOREIGN KEY (uploaded_by) REFERENCES operators(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- signatures and canned responses become HTML: convert the plain-text ones
-- (escape & < > and turn line breaks into <br>); rows already in HTML are skipped,
-- so running this file again changes nothing.
UPDATE departments
   SET signature = CONCAT('<p>', REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(signature, '&', '&amp;'), '<', '&lt;'), '>', '&gt;'), '\r\n', '\n'), '\n', '<br>'), '</p>')
 WHERE signature IS NOT NULL AND signature <> '' AND signature NOT LIKE '<%';

UPDATE canned_responses
   SET body = CONCAT('<p>', REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(body, '&', '&amp;'), '<', '&lt;'), '>', '&gt;'), '\r\n', '\n'), '\n', '<br>'), '</p>')
 WHERE body <> '' AND body NOT LIKE '<%';
