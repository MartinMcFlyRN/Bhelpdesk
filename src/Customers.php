<?php
declare(strict_types=1);

namespace Bhelpdesk;

/** Customer registry: created automatically when tickets are opened, editable by operators. */
final class Customers
{
    public const PER_PAGE = 25;

    /**
     * @return array{rows: array, total: int}
     */
    public static function search(string $q, int $page = 1): array
    {
        $where = '';
        $p = [];
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where = ' WHERE c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.company LIKE ?';
            $p = [$like, $like, $like, $like];
        }
        $total = (int) Db::value('SELECT COUNT(*) FROM customers c' . $where, $p);
        $offset = max(0, ($page - 1) * self::PER_PAGE);
        $rows = Db::all(
            'SELECT c.*,
                    COUNT(t.id) AS ticket_count,
                    SUM(t.status <> \'closed\') AS open_count,
                    MAX(t.updated_at) AS last_activity
               FROM customers c
               LEFT JOIN tickets t ON t.customer_id = c.id' . $where . '
              GROUP BY c.id
              ORDER BY COALESCE(MAX(t.updated_at), c.created_at) DESC
              LIMIT ' . self::PER_PAGE . ' OFFSET ' . $offset,
            $p
        );
        return ['rows' => $rows, 'total' => $total];
    }

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM customers WHERE id = ?', [$id]);
    }

    public static function tickets(int $customerId): array
    {
        return Db::all(
            'SELECT t.id, t.number, t.subject, t.status, t.priority, t.created_at, t.updated_at, d.name AS department_name
               FROM tickets t LEFT JOIN departments d ON d.id = t.department_id
              WHERE t.customer_id = ?
              ORDER BY t.updated_at DESC',
            [$customerId]
        );
    }

    /**
     * @return array{values: array, errors: list<string>}
     */
    public static function validate(array $in, ?int $id = null): array
    {
        $langs = array_keys(Lang::available(true));
        $v = [
            'name'     => trim((string) ($in['name'] ?? '')),
            'company'  => trim((string) ($in['company'] ?? '')),
            'email'    => mb_strtolower(trim((string) ($in['email'] ?? ''))),
            'phone'    => trim((string) preg_replace('/\s+/', ' ', (string) ($in['phone'] ?? ''))),
            'language' => (string) ($in['language'] ?? Lang::BASE),
            'notes'    => trim((string) ($in['notes'] ?? '')),
        ];
        $errors = [];
        if ($v['name'] === '' || mb_strlen($v['name']) > 100) {
            $errors[] = __('val.name_required');
        }
        if (mb_strlen($v['company']) > 150) {
            $errors[] = __('val.company_long');
        }
        if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || strlen($v['email']) > 190) {
            $errors[] = __('val.email_invalid');
        } elseif (Db::value('SELECT 1 FROM customers WHERE email = ? AND id <> ?', [$v['email'], (int) $id])) {
            $errors[] = __('val.customer_duplicate');
        }
        if (!self::phoneOk($v['phone'])) {
            $errors[] = __('val.phone_invalid');
        }
        if (!in_array($v['language'], $langs, true)) {
            $v['language'] = Lang::BASE;
        }
        if (mb_strlen($v['notes']) > 5000) {
            $errors[] = __('val.notes_long');
        }
        return ['values' => $v, 'errors' => $errors];
    }

    /** Optional phone: digits, spaces and + ( ) - . / only, at least 6 digits */
    public static function phoneOk(string $phone): bool
    {
        return $phone === '' || (preg_match('#^\+?[0-9 ()./-]{6,30}$#', $phone) && preg_match_all('/\d/', $phone) >= 6);
    }

    public static function create(array $v): int
    {
        return Db::insert(
            'INSERT INTO customers (name, company, email, phone, language, notes) VALUES (?, ?, ?, ?, ?, ?)',
            [$v['name'], $v['company'] ?: null, $v['email'], $v['phone'] ?: null, $v['language'], $v['notes'] ?: null]
        );
    }

    public static function update(int $id, array $v): void
    {
        Db::exec(
            'UPDATE customers SET name = ?, company = ?, email = ?, phone = ?, language = ?, notes = ? WHERE id = ?',
            [$v['name'], $v['company'] ?: null, $v['email'], $v['phone'] ?: null, $v['language'], $v['notes'] ?: null, $id]
        );
    }
}
