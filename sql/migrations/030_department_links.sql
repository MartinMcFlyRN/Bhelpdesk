-- Useful links per department, shown to its operators in the "Links" menu
CREATE TABLE IF NOT EXISTS department_links (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    department_id  INT UNSIGNED NOT NULL,
    name           VARCHAR(100) NOT NULL,
    url            VARCHAR(500) NOT NULL,
    sort_order     SMALLINT NOT NULL DEFAULT 0,
    KEY ix_department_links_dep (department_id, sort_order),
    CONSTRAINT fk_department_links_dep FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
