-- Slack notifications per department (incoming webhook)
CREATE TABLE IF NOT EXISTS department_slack (
    department_id  INT UNSIGNED NOT NULL PRIMARY KEY,
    webhook        TEXT NULL,                         -- incoming webhook URL, encrypted with storage/app.key
    notify_new     TINYINT(1) NOT NULL DEFAULT 1,     -- new tickets
    notify_reply   TINYINT(1) NOT NULL DEFAULT 1,     -- customer replies
    last_at        DATETIME NULL,                     -- last delivery attempt
    last_error     VARCHAR(255) NULL,                 -- NULL = last delivery succeeded
    CONSTRAINT fk_department_slack FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
