-- Billing: invoices to issue (one-off or recurring) for the customers
CREATE TABLE IF NOT EXISTS invoices (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id  INT UNSIGNED NOT NULL,
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
    CONSTRAINT fk_invoices_customer   FOREIGN KEY (customer_id) REFERENCES customers(id),
    CONSTRAINT fk_invoices_issued_by  FOREIGN KEY (issued_by)   REFERENCES operators(id) ON DELETE SET NULL,
    CONSTRAINT fk_invoices_created_by FOREIGN KEY (created_by)  REFERENCES operators(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
