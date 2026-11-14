<?php
declare(strict_types=1);

namespace Bhelpdesk;

final class Operators
{
    public const MIN_PASSWORD = 12;

    /** @var array<int, list<int>> */
    private static array $depCache = [];

    /** Departments assigned to an operator */
    public static function departmentIds(int $operatorId): array
    {
        return self::$depCache[$operatorId] ??= array_map('intval', array_column(
            Db::all('SELECT department_id FROM operator_departments WHERE operator_id = ?', [$operatorId]),
            'department_id'
        ));
    }

    public static function setDepartments(int $operatorId, array $departmentIds): void
    {
        Db::exec('DELETE FROM operator_departments WHERE operator_id = ?', [$operatorId]);
        foreach (array_unique(array_map('intval', $departmentIds)) as $d) {
            Db::exec('INSERT IGNORE INTO operator_departments (operator_id, department_id) SELECT ?, id FROM departments WHERE id = ?', [$operatorId, $d]);
        }
        unset(self::$depCache[$operatorId]);
    }

    /** Department names per operator, for lists: operator_id => [names] */
    public static function departmentNames(): array
    {
        $out = [];
        foreach (Db::all('SELECT od.operator_id, d.name FROM operator_departments od JOIN departments d ON d.id = od.department_id ORDER BY d.sort_order, d.name') as $r) {
            $out[(int) $r['operator_id']][] = $r['name'];
        }
        return $out;
    }

    public static function all(): array
    {
        return Db::all(
            'SELECT o.id, o.username, o.name, o.email, o.role, o.active, o.last_login_at, o.last_login_ip,
                    (SELECT COUNT(*) FROM tickets t WHERE t.assigned_to = o.id AND t.status <> \'closed\') AS open_tickets
               FROM operators o
              ORDER BY o.active DESC, o.name'
        );
    }

    public static function find(int $id): ?array
    {
        return Db::one('SELECT id, username, name, email, language, role, active, alert_new, alert_reply, alert_assign, last_login_at, last_login_ip, created_at FROM operators WHERE id = ?', [$id]);
    }

    /**
     * Validate create/edit form.
     * $existing = current row when editing (username is not editable).
     * @return array{values: array, errors: list<string>}
     */
    public static function validate(array $in, ?array $existing = null): array
    {
        $v = [
            'username' => $existing ? $existing['username'] : trim((string) ($in['username'] ?? '')),
            'name'     => trim((string) ($in['name'] ?? '')),
            'email'    => mb_strtolower(trim((string) ($in['email'] ?? ''))),
            'role'     => ($in['role'] ?? '') === 'admin' ? 'admin' : 'agent',
            'active'   => $existing ? (!empty($in['active']) ? 1 : 0) : 1,
            'password' => (string) ($in['password'] ?? ''),
            'departments' => array_values(array_unique(array_filter(array_map('intval', (array) ($in['departments'] ?? []))))),
        ];
        $errors = [];
        if (!$existing && !preg_match('/^[a-z0-9._-]{3,50}$/i', $v['username'])) {
            $errors[] = __('val.username');
        }
        if ($v['name'] === '' || mb_strlen($v['name']) > 100) {
            $errors[] = __('val.ops_name_required');
        }
        if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || strlen($v['email']) > 190) {
            $errors[] = __('val.email_invalid');
        }
        // agents work only on the tickets of their departments: at least one is needed
        if ($v['role'] === 'agent' && !$v['departments']) {
            $errors[] = __('val.ops_departments');
        }
        // password required on create, optional on edit
        if ((!$existing || $v['password'] !== '') && strlen($v['password']) < self::MIN_PASSWORD) {
            $errors[] = __('val.password_short');
        }
        if (!$errors) {
            $dup = Db::value(
                'SELECT 1 FROM operators WHERE (username = ? OR email = ?) AND id <> ?',
                [$v['username'], $v['email'], (int) ($existing['id'] ?? 0)]
            );
            if ($dup) {
                $errors[] = __('val.duplicate');
            }
        }
        return ['values' => $v, 'errors' => $errors];
    }

    public static function create(array $v): int
    {
        $id = Db::insert(
            'INSERT INTO operators (username, name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)',
            [$v['username'], $v['name'], $v['email'], password_hash($v['password'], PASSWORD_DEFAULT), $v['role']]
        );
        self::setDepartments($id, $v['departments'] ?? []);
        return $id;
    }

    public static function update(int $id, array $v): void
    {
        Db::exec('UPDATE operators SET name = ?, email = ?, role = ?, active = ? WHERE id = ?', [$v['name'], $v['email'], $v['role'], $v['active'], $id]);
        if ($v['password'] !== '') {
            Db::exec('UPDATE operators SET password_hash = ? WHERE id = ?', [password_hash($v['password'], PASSWORD_DEFAULT), $id]);
        }
        self::setDepartments($id, $v['departments'] ?? []);
    }

    /**
     * Validate the "my profile" form. Changing email or password requires the current password.
     * @return array{values: array, errors: list<string>}
     */
    public static function validateProfile(array $in, array $me): array
    {
        $v = [
            'name'             => trim((string) ($in['name'] ?? '')),
            'email'            => mb_strtolower(trim((string) ($in['email'] ?? ''))),
            'new_password'     => (string) ($in['new_password'] ?? ''),
            'confirm_password' => (string) ($in['confirm_password'] ?? ''),
            'current_password' => (string) ($in['current_password'] ?? ''),
            'alert_new'        => !empty($in['alert_new']),
            'alert_reply'      => !empty($in['alert_reply']),
            'alert_assign'     => !empty($in['alert_assign']),
        ];

        $errors = [];
        if ($v['name'] === '' || mb_strlen($v['name']) > 100) {
            $errors[] = __('val.ops_name_required');
        }
        if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || strlen($v['email']) > 190) {
            $errors[] = __('val.email_invalid');
        } elseif (Db::value('SELECT 1 FROM operators WHERE email = ? AND id <> ?', [$v['email'], (int) $me['id']])) {
            $errors[] = __('val.duplicate');
        }
        if ($v['new_password'] !== '') {
            if (strlen($v['new_password']) < self::MIN_PASSWORD) {
                $errors[] = __('val.password_short');
            } elseif ($v['new_password'] !== $v['confirm_password']) {
                $errors[] = __('val.password_mismatch');
            }
        }
        $sensitive = $v['new_password'] !== '' || $v['email'] !== mb_strtolower((string) $me['email']);
        if ($sensitive) {
            $hash = (string) Db::value('SELECT password_hash FROM operators WHERE id = ?', [(int) $me['id']]);
            if ($v['current_password'] === '' || !password_verify($v['current_password'], $hash)) {
                $errors[] = __('val.current_password');
            }
        }
        // never send passwords back to the form
        $v['current_password'] = $v['confirm_password'] = '';
        if ($errors) {
            $v['new_password'] = '';
        }
        return ['values' => $v, 'errors' => $errors];
    }

    public static function updateProfile(int $id, array $v): void
    {
        Db::exec(
            'UPDATE operators SET name = ?, email = ?, alert_new = ?, alert_reply = ?, alert_assign = ? WHERE id = ?',
            [$v['name'], $v['email'], (int) $v['alert_new'], (int) $v['alert_reply'], (int) $v['alert_assign'], $id]
        );
        if ($v['new_password'] !== '') {
            Db::exec('UPDATE operators SET password_hash = ? WHERE id = ?', [password_hash($v['new_password'], PASSWORD_DEFAULT), $id]);
        }
    }

    /** Active administrators other than $exceptId */
    public static function otherActiveAdmins(int $exceptId): int
    {
        return (int) Db::value("SELECT COUNT(*) FROM operators WHERE role = 'admin' AND active = 1 AND id <> ?", [$exceptId]);
    }

    public static function setActive(int $id, bool $active): void
    {
        Db::exec('UPDATE operators SET active = ? WHERE id = ?', [(int) $active, $id]);
    }
}
