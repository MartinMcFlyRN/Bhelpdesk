<?php
declare(strict_types=1);

namespace Bhelpdesk;

/**
 * Email identities (/op/config/email): an address with its SMTP (sending) and
 * POP3 (receiving) accounts.
 *
 * - Every identity belongs to a department: tickets created from the mail it
 *   downloads go to that department.
 * - Every department may choose the identity it sends with
 *   (departments.send_identity_id), which can differ from the receiving one.
 * - Passwords are encrypted with Crypto and never sent back to the browser.
 */
final class MailIdentities
{
    public const SECURITY = ['ssl', 'starttls', 'none'];
    public const KEEP = ['delete', 'days', 'forever'];

    public static function all(): array
    {
        return Db::all(
            'SELECT i.*, d.name AS department_name,
                    (SELECT GROUP_CONCAT(x.name ORDER BY x.name SEPARATOR \', \') FROM departments x WHERE x.send_identity_id = i.id) AS sending_for
               FROM mail_identities i JOIN departments d ON d.id = i.department_id
              ORDER BY i.active DESC, i.email'
        );
    }

    public static function options(): array
    {
        return Db::all('SELECT id, name, email FROM mail_identities WHERE active = 1 ORDER BY email');
    }

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM mail_identities WHERE id = ?', [$id]);
    }

    /**
     * Identity used to send mail for a department:
     * its chosen sending identity, else an identity receiving for it, else the default one.
     */
    public static function forDepartment(?int $departmentId): ?array
    {
        if ($departmentId) {
            $id = Db::value(
                'SELECT i.id FROM departments d JOIN mail_identities i ON i.id = d.send_identity_id AND i.active = 1 WHERE d.id = ?',
                [$departmentId]
            ) ?: Db::value(
                "SELECT id FROM mail_identities WHERE department_id = ? AND active = 1 AND smtp_host <> '' ORDER BY id LIMIT 1",
                [$departmentId]
            );
            if ($id) {
                return self::find((int) $id);
            }
        }
        return self::defaultIdentity();
    }

    /** Identity for system emails (passwords, ...) chosen in /op/config/email */
    public static function defaultIdentity(): ?array
    {
        $id = (int) config('mail.default_identity', 0);
        $i = $id ? self::find($id) : null;
        return $i && $i['active'] ? $i : null;
    }

    public static function setDefault(?int $id): void
    {
        if ($id && !self::find($id)) {
            $id = null;
        }
        Db::exec(
            'INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()',
            ['mail.default_identity', json_encode($id)]
        );
    }

    /**
     * @return array{values: array, errors: list<string>}
     */
    public static function validate(array $in, ?array $existing = null): array
    {
        $int = static fn ($k, $d) => (int) ($in[$k] ?? $d);
        $str = static fn ($k) => trim((string) ($in[$k] ?? ''));
        $v = [
            'name'          => $str('name'),
            'email'         => mb_strtolower($str('email')),
            'department_id' => $int('department_id', 0),
            'active'        => !empty($in['active']) ? 1 : 0,
            'smtp_host'     => $str('smtp_host'),
            'smtp_port'     => $int('smtp_port', 587),
            'smtp_security' => in_array($in['smtp_security'] ?? '', self::SECURITY, true) ? $in['smtp_security'] : 'starttls',
            'smtp_user'     => $str('smtp_user'),
            'smtp_verify'   => !empty($in['smtp_verify']) ? 1 : 0,
            'pop_enabled'   => !empty($in['pop_enabled']) ? 1 : 0,
            'pop_host'      => $str('pop_host'),
            'pop_port'      => $int('pop_port', 995),
            'pop_security'  => in_array($in['pop_security'] ?? '', self::SECURITY, true) ? $in['pop_security'] : 'ssl',
            'pop_user'      => $str('pop_user'),
            'pop_verify'    => !empty($in['pop_verify']) ? 1 : 0,
            'pop_keep'      => in_array($in['pop_keep'] ?? '', self::KEEP, true) ? $in['pop_keep'] : 'days',
            'pop_keep_days' => $int('pop_keep_days', 30),
            // empty password field = keep the saved one
            'smtp_pass_new' => (string) ($in['smtp_pass'] ?? ''),
            'pop_pass_new'  => (string) ($in['pop_pass'] ?? ''),
            'smtp_pass_clear' => !empty($in['smtp_pass_clear']),
        ];
        $e = [];
        $host = '/^[A-Za-z0-9]([A-Za-z0-9.-]{0,251}[A-Za-z0-9])?$/';
        if ($v['name'] === '' || mb_strlen($v['name']) > 100) {
            $e[] = __('mi.err_name');
        }
        if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || strlen($v['email']) > 190) {
            $e[] = __('mi.err_email');
        } elseif (Db::value('SELECT 1 FROM mail_identities WHERE email = ? AND id <> ?', [$v['email'], (int) ($existing['id'] ?? 0)])) {
            $e[] = __('mi.err_email_dup');
        }
        if (!$v['department_id'] || !Db::value('SELECT 1 FROM departments WHERE id = ?', [$v['department_id']])) {
            $e[] = __('mi.err_department');
        }
        if ($v['smtp_host'] !== '') {
            if (!preg_match($host, $v['smtp_host'])) {
                $e[] = __('mi.err_host', ['what' => 'SMTP']);
            }
            if ($v['smtp_port'] < 1 || $v['smtp_port'] > 65535) {
                $e[] = __('mi.err_port', ['what' => 'SMTP']);
            }
        }
        if ($v['pop_enabled']) {
            if ($v['pop_host'] === '' || !preg_match($host, $v['pop_host'])) {
                $e[] = __('mi.err_host', ['what' => 'POP3']);
            }
            if ($v['pop_port'] < 1 || $v['pop_port'] > 65535) {
                $e[] = __('mi.err_port', ['what' => 'POP3']);
            }
            if ($v['pop_user'] === '') {
                $e[] = __('mi.err_pop_user');
            }
            if ($v['pop_pass_new'] === '' && empty($existing['pop_pass'])) {
                $e[] = __('mi.err_pop_pass');
            }
            if ($v['pop_keep'] === 'days' && ($v['pop_keep_days'] < 1 || $v['pop_keep_days'] > 3650)) {
                $e[] = __('mi.err_keep_days');
            }
        }
        return ['values' => $v, 'errors' => $e];
    }

    public static function save(array $v, ?int $id = null): int
    {
        $fields = ['name', 'email', 'department_id', 'active', 'smtp_host', 'smtp_port', 'smtp_security', 'smtp_user', 'smtp_verify',
            'pop_enabled', 'pop_host', 'pop_port', 'pop_security', 'pop_user', 'pop_verify', 'pop_keep', 'pop_keep_days'];
        $data = array_intersect_key($v, array_flip($fields));
        if ($v['smtp_pass_new'] !== '') {
            $data['smtp_pass'] = Crypto::encrypt($v['smtp_pass_new']);
        } elseif ($v['smtp_pass_clear']) {
            $data['smtp_pass'] = '';
        }
        if ($v['pop_pass_new'] !== '') {
            $data['pop_pass'] = Crypto::encrypt($v['pop_pass_new']);
        }
        if ($id) {
            $set = implode(', ', array_map(static fn ($k) => $k . ' = ?', array_keys($data)));
            Db::exec('UPDATE mail_identities SET ' . $set . ' WHERE id = ?', array_merge(array_values($data), [$id]));
            return $id;
        }
        $data += ['smtp_pass' => '', 'pop_pass' => ''];
        return Db::insert(
            'INSERT INTO mail_identities (' . implode(', ', array_keys($data)) . ') VALUES (' . implode(', ', array_fill(0, count($data), '?')) . ')',
            array_values($data)
        );
    }

    public static function delete(int $id): void
    {
        Db::exec('DELETE FROM mail_identities WHERE id = ?', [$id]);
        if ((int) config('mail.default_identity', 0) === $id) {
            self::setDefault(null);
        }
    }

    public static function smtp(array $i): Smtp
    {
        return new Smtp($i['smtp_host'], (int) $i['smtp_port'], $i['smtp_security'], (string) $i['smtp_user'], Crypto::decrypt($i['smtp_pass']), (bool) $i['smtp_verify']);
    }

    public static function pop3(array $i): Pop3
    {
        return new Pop3($i['pop_host'], (int) $i['pop_port'], $i['pop_security'], (string) $i['pop_user'], Crypto::decrypt($i['pop_pass']), (bool) $i['pop_verify']);
    }

    /** Record the result of the last POP3 check, shown in the list */
    public static function markPop(int $id, bool $ok, string $message): void
    {
        Db::exec('UPDATE mail_identities SET pop_checked_at = NOW(), pop_status = ? WHERE id = ?', [($ok ? 'OK: ' : 'ERROR: ') . mb_substr($message, 0, 240), $id]);
    }
}
