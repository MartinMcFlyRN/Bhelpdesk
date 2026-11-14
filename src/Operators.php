<?php
declare(strict_types=1);

namespace Bhelpdesk;

final class Operators
{
    public const MIN_PASSWORD = 12;

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
        return Db::one('SELECT id, username, name, email, role, active, last_login_at, last_login_ip, created_at FROM operators WHERE id = ?', [$id]);
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
        return Db::insert(
            'INSERT INTO operators (username, name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)',
            [$v['username'], $v['name'], $v['email'], password_hash($v['password'], PASSWORD_DEFAULT), $v['role']]
        );
    }

    public static function update(int $id, array $v): void
    {
        Db::exec('UPDATE operators SET name = ?, email = ?, role = ?, active = ? WHERE id = ?', [$v['name'], $v['email'], $v['role'], $v['active'], $id]);
        if ($v['password'] !== '') {
            Db::exec('UPDATE operators SET password_hash = ? WHERE id = ?', [password_hash($v['password'], PASSWORD_DEFAULT), $id]);
        }
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
        Db::exec('UPDATE operators SET name = ?, email = ? WHERE id = ?', [$v['name'], $v['email'], $id]);
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
