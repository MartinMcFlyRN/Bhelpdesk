-- Bhelpdesk - MariaDB schema (>= 10.5)
-- mysql -u root -p bhelpdesk < sql/schema.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS departments (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    active      TINYINT(1) NOT NULL DEFAULT 1,
    signature   TEXT NULL,
    send_identity_id INT UNSIGNED NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS operators (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username       VARCHAR(50)  NOT NULL,
    name           VARCHAR(100) NOT NULL,
    email          VARCHAR(190) NOT NULL,
    language       VARCHAR(10)  NULL,                -- language of the alert emails (NULL = default)
    password_hash  VARCHAR(255) NOT NULL,
    role           ENUM('admin','agent') NOT NULL DEFAULT 'agent',
    active         TINYINT(1) NOT NULL DEFAULT 1,
    alert_new      TINYINT(1) NOT NULL DEFAULT 1,    -- email: new tickets in my departments
    alert_reply    TINYINT(1) NOT NULL DEFAULT 1,    -- email: customer replies (my tickets / unassigned of my departments)
    alert_assign   TINYINT(1) NOT NULL DEFAULT 1,    -- email: ticket assigned to me
    last_login_at  DATETIME NULL,
    last_login_ip  VARCHAR(45) NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_operators_username (username),
    UNIQUE KEY uq_operators_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Departments handled by each agent (administrators see all departments)
CREATE TABLE IF NOT EXISTS operator_departments (
    operator_id    INT UNSIGNED NOT NULL,
    department_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (operator_id, department_id),
    KEY ix_operator_departments_dep (department_id),
    CONSTRAINT fk_opdep_operator   FOREIGN KEY (operator_id)   REFERENCES operators(id)   ON DELETE CASCADE,
    CONSTRAINT fk_opdep_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customers (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    company     VARCHAR(150) NULL,
    email       VARCHAR(190) NOT NULL,
    phone       VARCHAR(30)  NULL,
    language    VARCHAR(10)  NOT NULL DEFAULT 'en',
    notes       TEXT         NULL,
    password_hash     VARCHAR(255) NULL,
    email_verified_at DATETIME NULL,
    last_login_at     DATETIME NULL,
    last_login_ip     VARCHAR(45) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_customers_email (email),
    KEY ix_customers_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tickets (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    number         VARCHAR(16)  NOT NULL,
    customer_id    INT UNSIGNED NOT NULL,
    department_id  INT UNSIGNED NULL,
    assigned_to    INT UNSIGNED NULL,
    subject        VARCHAR(255) NOT NULL,
    phone          VARCHAR(30)  NULL,
    status         ENUM('open','answered','on_hold','closed') NOT NULL DEFAULT 'open',
    priority       ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    created_ip     VARCHAR(45)  NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at      DATETIME NULL,
    merged_into    INT UNSIGNED NULL,
    UNIQUE KEY uq_tickets_number (number),
    KEY ix_tickets_status_updated (status, updated_at),
    KEY ix_tickets_assigned (assigned_to),
    KEY ix_tickets_customer (customer_id),
    CONSTRAINT fk_tickets_customer   FOREIGN KEY (customer_id)   REFERENCES customers(id),
    CONSTRAINT fk_tickets_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    CONSTRAINT fk_tickets_operator   FOREIGN KEY (assigned_to)   REFERENCES operators(id)   ON DELETE SET NULL,
    CONSTRAINT fk_tickets_merged     FOREIGN KEY (merged_into)   REFERENCES tickets(id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket_messages (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id    INT UNSIGNED NOT NULL,
    author_type  ENUM('customer','operator','system') NOT NULL,
    operator_id  INT UNSIGNED NULL,
    customer_id  INT UNSIGNED NULL,
    body         MEDIUMTEXT NOT NULL,
    body_format  ENUM('text','html') NOT NULL DEFAULT 'text',
    is_internal  TINYINT(1) NOT NULL DEFAULT 0,
    via_email    TINYINT(1) NOT NULL DEFAULT 0,
    ip           VARCHAR(45) NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY ix_messages_ticket (ticket_id, created_at),
    CONSTRAINT fk_messages_ticket   FOREIGN KEY (ticket_id)   REFERENCES tickets(id)   ON DELETE CASCADE,
    CONSTRAINT fk_messages_operator FOREIGN KEY (operator_id) REFERENCES operators(id) ON DELETE SET NULL,
    CONSTRAINT fk_messages_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Single-use links to create / reset a customer password (hash only)
CREATE TABLE IF NOT EXISTS customer_tokens (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id  INT UNSIGNED NOT NULL,
    token_hash   CHAR(64)     NOT NULL,
    expires_at   DATETIME     NOT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_customer_tokens_hash (token_hash),
    KEY ix_customer_tokens_customer (customer_id),
    CONSTRAINT fk_ctokens_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Direct ticket links (hash only); expiry = last public message + customer_link_days
CREATE TABLE IF NOT EXISTS ticket_links (
    token_hash    CHAR(64)     NOT NULL PRIMARY KEY,
    ticket_id     INT UNSIGNED NOT NULL,
    customer_id   INT UNSIGNED NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at  DATETIME     NULL,
    KEY ix_ticket_links_ticket (ticket_id, customer_id),
    CONSTRAINT fk_tlinks_ticket   FOREIGN KEY (ticket_id)   REFERENCES tickets(id)   ON DELETE CASCADE,
    CONSTRAINT fk_tlinks_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Additional customers of a ticket (the main one is tickets.customer_id)
CREATE TABLE IF NOT EXISTS ticket_customers (
    ticket_id    INT UNSIGNED NOT NULL,
    customer_id  INT UNSIGNED NOT NULL,
    added_by     INT UNSIGNED NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ticket_id, customer_id),
    KEY ix_ticket_customers_customer (customer_id),
    CONSTRAINT fk_tc_ticket   FOREIGN KEY (ticket_id)   REFERENCES tickets(id)   ON DELETE CASCADE,
    CONSTRAINT fk_tc_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    CONSTRAINT fk_tc_operator FOREIGN KEY (added_by)    REFERENCES operators(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attachments (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id      INT UNSIGNED NOT NULL,
    message_id     INT UNSIGNED NOT NULL,
    original_name  VARCHAR(190) NOT NULL,
    stored_name    VARCHAR(64)  NOT NULL,
    mime           VARCHAR(100) NOT NULL,
    size           INT UNSIGNED NOT NULL,
    sha256         CHAR(64)     NOT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_attachments_stored (stored_name),
    KEY ix_attachments_message (message_id),
    KEY ix_attachments_ticket (ticket_id),
    CONSTRAINT fk_attachments_ticket  FOREIGN KEY (ticket_id)  REFERENCES tickets(id)         ON DELETE CASCADE,
    CONSTRAINT fk_attachments_message FOREIGN KEY (message_id) REFERENCES ticket_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email identities (SMTP + POP3); departments.send_identity_id references them
CREATE TABLE IF NOT EXISTS mail_identities (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    email           VARCHAR(190) NOT NULL,
    department_id   INT UNSIGNED NOT NULL,          -- tickets from downloaded mail go here
    active          TINYINT(1)   NOT NULL DEFAULT 1,
    smtp_host       VARCHAR(253) NOT NULL DEFAULT '',
    smtp_port       SMALLINT UNSIGNED NOT NULL DEFAULT 587,
    smtp_security   ENUM('ssl','starttls','none') NOT NULL DEFAULT 'starttls',
    smtp_user       VARCHAR(190) NOT NULL DEFAULT '',
    smtp_pass       TEXT NULL,                      -- encrypted (Crypto)
    smtp_verify     TINYINT(1)   NOT NULL DEFAULT 1,
    pop_enabled     TINYINT(1)   NOT NULL DEFAULT 0,
    pop_host        VARCHAR(253) NOT NULL DEFAULT '',
    pop_port        SMALLINT UNSIGNED NOT NULL DEFAULT 995,
    pop_security    ENUM('ssl','starttls','none') NOT NULL DEFAULT 'ssl',
    pop_user        VARCHAR(190) NOT NULL DEFAULT '',
    pop_pass        TEXT NULL,                      -- encrypted (Crypto)
    pop_verify      TINYINT(1)   NOT NULL DEFAULT 1,
    pop_keep        ENUM('delete','days','forever') NOT NULL DEFAULT 'days',
    pop_keep_days   SMALLINT UNSIGNED NOT NULL DEFAULT 30,  -- leave on the server the last N days
    pop_checked_at  DATETIME NULL,
    pop_status      VARCHAR(255) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mail_identities_email (email),
    KEY ix_mail_identities_department (department_id),
    CONSTRAINT fk_mail_identities_department FOREIGN KEY (department_id) REFERENCES departments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE departments ADD CONSTRAINT fk_departments_send_identity FOREIGN KEY (send_identity_id) REFERENCES mail_identities(id) ON DELETE SET NULL;

-- Email sent and received (duplicates, threading, POP3 retention, log)
CREATE TABLE IF NOT EXISTS mail_messages (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    direction          ENUM('in','out') NOT NULL,
    identity_id        INT UNSIGNED NULL,
    pop_uid            VARCHAR(255) NULL,             -- UIDL on the POP3 server (incoming)
    message_id         VARCHAR(255) NULL,             -- without < >
    ticket_id          INT UNSIGNED NULL,
    ticket_message_id  INT UNSIGNED NULL,
    address            VARCHAR(190) NULL,             -- sender (in) / recipient (out)
    subject            VARCHAR(255) NULL,
    status             VARCHAR(16)  NOT NULL,         -- in: ticket, reply, ignored, error / out: sent, error
    note               VARCHAR(255) NULL,
    raw_path           VARCHAR(100) NULL,             -- source of the received email (storage/mail, gzip)
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mail_messages_uid (identity_id, pop_uid),
    KEY ix_mail_messages_tmessage (ticket_message_id),
    KEY ix_mail_messages_msgid (message_id),
    KEY ix_mail_messages_ticket (ticket_id),
    KEY ix_mail_messages_address (address, created_at),
    CONSTRAINT fk_mail_messages_identity FOREIGN KEY (identity_id) REFERENCES mail_identities(id) ON DELETE SET NULL,
    CONSTRAINT fk_mail_messages_ticket   FOREIGN KEY (ticket_id)   REFERENCES tickets(id)         ON DELETE SET NULL,
    CONSTRAINT fk_mail_messages_tmessage FOREIGN KEY (ticket_message_id) REFERENCES ticket_messages(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Images inserted with the editor (public URLs /customer/media/<file_name>)
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

-- Canned responses (/op/config/canned)
CREATE TABLE IF NOT EXISTS canned_responses (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title          VARCHAR(150) NOT NULL,
    body           TEXT         NOT NULL,
    department_id  INT UNSIGNED NULL,
    active         TINYINT(1)   NOT NULL DEFAULT 1,
    updated_by     INT UNSIGNED NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_canned_title (title),
    KEY ix_canned_department (department_id),
    CONSTRAINT fk_canned_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    CONSTRAINT fk_canned_operator   FOREIGN KEY (updated_by)    REFERENCES operators(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings edited from /op/config/general (override config/config.php)
CREATE TABLE IF NOT EXISTS settings (
    name        VARCHAR(64)  NOT NULL PRIMARY KEY,
    value       TEXT         NOT NULL,
    updated_by  INT UNSIGNED NULL,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_settings_operator FOREIGN KEY (updated_by) REFERENCES operators(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Generic rate limiting (operator login, ticket creation, status lookup)
CREATE TABLE IF NOT EXISTS throttle (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    action      VARCHAR(32) NOT NULL,
    ip          VARCHAR(45) NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY ix_throttle_lookup (action, ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO departments (name, sort_order)
SELECT * FROM (SELECT 'Technical support', 1 UNION ALL SELECT 'Billing', 2 UNION ALL SELECT 'Sales', 3) AS d
WHERE NOT EXISTS (SELECT 1 FROM departments);

-- Automatic emails edited in /op/config/templates (missing rows = built-in defaults)
CREATE TABLE IF NOT EXISTS email_templates (
    code        VARCHAR(40)  NOT NULL PRIMARY KEY,
    subject     VARCHAR(255) NOT NULL,
    body        MEDIUMTEXT   NOT NULL,          -- sanitized HTML with {placeholders}
    updated_by  INT UNSIGNED NULL,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_email_templates_operator FOREIGN KEY (updated_by) REFERENCES operators(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tasks for operators: active from due_at, assigned to everyone, a department or an operator
CREATE TABLE IF NOT EXISTS tasks (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title          VARCHAR(200) NOT NULL,
    description    MEDIUMTEXT   NULL,                    -- sanitized HTML
    due_at         DATETIME     NOT NULL,                -- from this moment the task is shown as "to do"
    has_time       TINYINT(1)   NOT NULL DEFAULT 0,      -- 0 = only the day matters
    target         ENUM('all','department','operator') NOT NULL DEFAULT 'all',
    department_id  INT UNSIGNED NULL,
    operator_id    INT UNSIGNED NULL,
    ticket_id      INT UNSIGNED NULL,                    -- task created from a ticket
    created_by     INT UNSIGNED NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    done_at        DATETIME NULL,
    done_by        INT UNSIGNED NULL,
    KEY ix_tasks_active (done_at, due_at),
    KEY ix_tasks_operator (operator_id),
    KEY ix_tasks_department (department_id),
    KEY ix_tasks_ticket (ticket_id),
    CONSTRAINT fk_tasks_ticket     FOREIGN KEY (ticket_id)     REFERENCES tickets(id)     ON DELETE SET NULL,
    CONSTRAINT fk_tasks_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    CONSTRAINT fk_tasks_operator   FOREIGN KEY (operator_id)   REFERENCES operators(id)   ON DELETE CASCADE,
    CONSTRAINT fk_tasks_created_by FOREIGN KEY (created_by)    REFERENCES operators(id)   ON DELETE SET NULL,
    CONSTRAINT fk_tasks_done_by    FOREIGN KEY (done_by)       REFERENCES operators(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Billing: invoices to issue (one-off or recurring) for the customers
CREATE TABLE IF NOT EXISTS invoices (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id  INT UNSIGNED NOT NULL,
    ticket_id    INT UNSIGNED NULL,                            -- invoice created from a ticket
    description  TEXT          NOT NULL,
    amount       DECIMAL(12,2) NOT NULL,
    start_date   DATE          NOT NULL,                       -- billing starts on this day
    recurrence   ENUM('once','monthly','bimonthly','yearly') NOT NULL DEFAULT 'once',
    issued       TINYINT(1)    NOT NULL DEFAULT 0,             -- 0 = to issue, 1 = issued
    issued_at    DATETIME NULL,
    issued_by    INT UNSIGNED NULL,
    created_by   INT UNSIGNED NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY ix_invoices_customer (customer_id),
    KEY ix_invoices_issued (issued, start_date),
    KEY ix_invoices_ticket (ticket_id),
    CONSTRAINT fk_invoices_ticket     FOREIGN KEY (ticket_id)   REFERENCES tickets(id)   ON DELETE SET NULL,
    CONSTRAINT fk_invoices_customer   FOREIGN KEY (customer_id) REFERENCES customers(id),
    CONSTRAINT fk_invoices_issued_by  FOREIGN KEY (issued_by)   REFERENCES operators(id) ON DELETE SET NULL,
    CONSTRAINT fk_invoices_created_by FOREIGN KEY (created_by)  REFERENCES operators(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
