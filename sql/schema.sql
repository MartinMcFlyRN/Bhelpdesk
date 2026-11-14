-- Bhelpdesk - MariaDB schema (>= 10.5)
-- mysql -u root -p bhelpdesk < sql/schema.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS departments (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    active      TINYINT(1) NOT NULL DEFAULT 1,
    signature   TEXT NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS operators (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username       VARCHAR(50)  NOT NULL,
    name           VARCHAR(100) NOT NULL,
    email          VARCHAR(190) NOT NULL,
    password_hash  VARCHAR(255) NOT NULL,
    role           ENUM('admin','agent') NOT NULL DEFAULT 'agent',
    active         TINYINT(1) NOT NULL DEFAULT 1,
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
    UNIQUE KEY uq_tickets_number (number),
    KEY ix_tickets_status_updated (status, updated_at),
    KEY ix_tickets_assigned (assigned_to),
    KEY ix_tickets_customer (customer_id),
    CONSTRAINT fk_tickets_customer   FOREIGN KEY (customer_id)   REFERENCES customers(id),
    CONSTRAINT fk_tickets_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    CONSTRAINT fk_tickets_operator   FOREIGN KEY (assigned_to)   REFERENCES operators(id)   ON DELETE SET NULL
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
