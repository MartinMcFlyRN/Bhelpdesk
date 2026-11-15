-- On-call rota: one operator per week (Monday to Sunday) plus optional single-day overrides
CREATE TABLE IF NOT EXISTS oncall_weeks (
    week_start   DATE NOT NULL PRIMARY KEY,           -- Monday of the week
    operator_id  INT UNSIGNED NOT NULL,
    updated_by   INT UNSIGNED NULL,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_oncall_weeks_operator FOREIGN KEY (operator_id) REFERENCES operators(id) ON DELETE CASCADE,
    CONSTRAINT fk_oncall_weeks_updated_by FOREIGN KEY (updated_by) REFERENCES operators(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS oncall_days (
    day          DATE NOT NULL PRIMARY KEY,           -- replaces the operator of the week on this day
    operator_id  INT UNSIGNED NOT NULL,
    updated_by   INT UNSIGNED NULL,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_oncall_days_operator FOREIGN KEY (operator_id) REFERENCES operators(id) ON DELETE CASCADE,
    CONSTRAINT fk_oncall_days_updated_by FOREIGN KEY (updated_by) REFERENCES operators(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
