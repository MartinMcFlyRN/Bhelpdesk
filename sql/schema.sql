-- Bhelpdesk - MariaDB schema (>= 10.5)
-- mysql -u root -p bhelpdesk < sql/schema.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS departments (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    active      TINYINT(1) NOT NULL DEFAULT 1,
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

CREATE TABLE IF NOT EXISTS customers (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(190) NOT NULL,
    language    VARCHAR(10)  NOT NULL DEFAULT 'en',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_customers_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tickets (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    number         VARCHAR(16)  NOT NULL,
    access_token   CHAR(64)     NOT NULL,
    customer_id    INT UNSIGNED NOT NULL,
    department_id  INT UNSIGNED NULL,
    assigned_to    INT UNSIGNED NULL,
    subject        VARCHAR(255) NOT NULL,
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
    body         MEDIUMTEXT NOT NULL,
    is_internal  TINYINT(1) NOT NULL DEFAULT 0,
    ip           VARCHAR(45) NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY ix_messages_ticket (ticket_id, created_at),
    CONSTRAINT fk_messages_ticket   FOREIGN KEY (ticket_id)   REFERENCES tickets(id)   ON DELETE CASCADE,
    CONSTRAINT fk_messages_operator FOREIGN KEY (operator_id) REFERENCES operators(id) ON DELETE SET NULL
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
