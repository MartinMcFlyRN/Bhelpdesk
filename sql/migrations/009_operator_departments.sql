-- Operators <-> departments (v0.10). New installs get this table from sql/schema.sql.
-- Agents see only the tickets of their departments (plus tickets assigned to them);
-- administrators see everything.

CREATE TABLE IF NOT EXISTS operator_departments (
    operator_id    INT UNSIGNED NOT NULL,
    department_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (operator_id, department_id),
    KEY ix_operator_departments_dep (department_id),
    CONSTRAINT fk_opdep_operator   FOREIGN KEY (operator_id)   REFERENCES operators(id)   ON DELETE CASCADE,
    CONSTRAINT fk_opdep_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Upgrade: existing agents keep seeing every ticket, so they get all current departments
-- (only agents that have no department yet, so re-running this file changes nothing).
INSERT IGNORE INTO operator_departments (operator_id, department_id)
    SELECT o.id, d.id FROM operators o CROSS JOIN departments d
     WHERE o.role = 'agent'
       AND NOT EXISTS (SELECT 1 FROM operator_departments x WHERE x.operator_id = o.id);
