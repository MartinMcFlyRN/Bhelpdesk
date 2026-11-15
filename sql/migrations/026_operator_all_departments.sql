-- Department access no longer depends on the role: "all departments" is a flag of the operator.
-- Existing administrators keep seeing every department.
ALTER TABLE operators ADD COLUMN IF NOT EXISTS all_departments TINYINT(1) NOT NULL DEFAULT 0 AFTER role;
UPDATE operators SET all_departments = 1 WHERE role = 'admin' AND NOT EXISTS (SELECT 1 FROM operator_departments od WHERE od.operator_id = operators.id);
