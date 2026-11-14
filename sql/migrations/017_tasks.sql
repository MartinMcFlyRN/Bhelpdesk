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
    created_by     INT UNSIGNED NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    done_at        DATETIME NULL,
    done_by        INT UNSIGNED NULL,
    KEY ix_tasks_active (done_at, due_at),
    KEY ix_tasks_operator (operator_id),
    KEY ix_tasks_department (department_id),
    CONSTRAINT fk_tasks_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    CONSTRAINT fk_tasks_operator   FOREIGN KEY (operator_id)   REFERENCES operators(id)   ON DELETE CASCADE,
    CONSTRAINT fk_tasks_created_by FOREIGN KEY (created_by)    REFERENCES operators(id)   ON DELETE SET NULL,
    CONSTRAINT fk_tasks_done_by    FOREIGN KEY (done_by)       REFERENCES operators(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
