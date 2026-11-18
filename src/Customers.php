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
    public static function search(string $q, int $page = 1, ?array $op = null): array
    {
        // ticket counts only include the tickets the operator may see
        [$vis, $vp] = $op ? Tickets::visibility($op) : ['1 = 1', []];
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
               LEFT JOIN tickets t ON t.customer_id = c.id AND t.merged_into IS NULL AND ' . $vis . $where . '
              GROUP BY c.id
              ORDER BY COALESCE(MAX(t.updated_at), c.created_at) DESC
              LIMIT ' . self::PER_PAGE . ' OFFSET ' . $offset,
            array_merge($vp, $p)
        );
        return ['rows' => $rows, 'total' => $total];
    }

    public static function findByEmail(string $email): ?array
    {
        return Db::one('SELECT * FROM customers WHERE email = ?', [mb_strtolower(trim($email))]);
    }

    /** Quick search for the autocomplete in the ticket page */
    public static function lookup(string $q, int $limit = 10): array
    {
        if (mb_strlen($q) < 2) {
            return [];
        }
        $like = '%' . addcslashes($q, '%_\\') . '%';
        return Db::all(
            'SELECT id, name, email, company FROM customers
              WHERE name LIKE ? OR email LIKE ? OR company LIKE ?
              ORDER BY name LIMIT ' . max(1, min(25, $limit)),
            [$like, $like, $like]
        );
    }

    /**
     * Customer picked from the ticket page: existing one by email, or a new one
     * when a name is given too.
     * @return array{id: ?int, error: ?string}
     */
    public static function resolve(string $email, string $name): array
    {
        $email = mb_strtolower(trim($email));
        $name = trim($name);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            return ['id' => null, 'error' => __('val.email_invalid')];
        }
        $existing = self::findByEmail($email);
        if ($existing) {
            return ['id' => (int) $existing['id'], 'error' => null];
        }
        if ($name === '' || mb_strlen($name) > 100) {
            return ['id' => null, 'error' => __('val.customer_new_needs_name')];
        }
        $id = self::create(['name' => $name, 'company' => '', 'email' => $email, 'phone' => '', 'language' => (string) config('default_language', 'en'), 'notes' => '']);
        return ['id' => $id, 'error' => null];
    }

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM customers WHERE id = ?', [$id]);
    }

    /**
     * Tickets where the customer is the main customer or an additional one (role = main|cc).
     * Used for the customer's own "My tickets" page ($op = null) and, limited to
     * the tickets the operator may see, for the customer page in /op.
     */
    public static function tickets(int $customerId, ?array $op = null): array
    {
        [$vis, $vp] = $op ? Tickets::visibility($op) : ['1 = 1', []];
        return Db::all(
            'SELECT t.id, t.number, t.subject, t.status, t.priority, t.created_at, t.updated_at, d.name AS department_name,
                    IF(t.customer_id = ?, \'main\', \'cc\') AS role
               FROM tickets t LEFT JOIN departments d ON d.id = t.department_id
              WHERE t.merged_into IS NULL AND (t.customer_id = ?
                 OR EXISTS (SELECT 1 FROM ticket_customers tc WHERE tc.ticket_id = t.id AND tc.customer_id = ?))
                AND ' . $vis . '
              ORDER BY t.updated_at DESC',
            array_merge([$customerId, $customerId, $customerId], $vp)
        );
    }

    /**
     * Tickets of a customer (main or added) for the customer area, paginated in SQL.
     * $f: status ('' = any), closed (true: closed only, false: not closed),
     *     q (number or part of the subject), page, per.
     * @return array{rows: list<array>, total: int, page: int, pages: int}
     */
    public static function ticketPage(int $customerId, array $f): array
    {
        [$where, $params] = self::ticketWhere($customerId, (string) ($f['q'] ?? ''));
        $status = (string) ($f['status'] ?? '');
        if ($status !== '') {
            $where .= ' AND t.status = ?';
            $params[] = $status;
        }
        $where .= !empty($f['closed']) ? " AND t.status = 'closed'" : " AND t.status <> 'closed'";
        $per = max(1, (int) ($f['per'] ?? 20));
        $total = (int) Db::value('SELECT COUNT(*) FROM ' . self::TICKETS_OF . ' WHERE ' . $where, $params);
        $pages = max(1, (int) ceil($total / $per));
        $page = min($pages, max(1, (int) ($f['page'] ?? 1)));
        $rows = $total === 0 ? [] : Db::all(
            'SELECT t.id, t.number, t.subject, t.status, t.priority, t.created_at, t.updated_at, d.name AS department_name,
                    IF(t.customer_id = ?, \'main\', \'cc\') AS role
               FROM ' . self::TICKETS_OF . ' LEFT JOIN departments d ON d.id = t.department_id
              WHERE ' . $where . '
              ORDER BY t.updated_at DESC, t.id DESC
              LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per),
            array_merge([$customerId], $params)
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** Number of tickets of a customer per status (with the same search) */
    public static function ticketCounts(int $customerId, string $q = ''): array
    {
        [$where, $params] = self::ticketWhere($customerId, $q);
        $out = [];
        foreach (Db::all('SELECT t.status, COUNT(*) AS n FROM ' . self::TICKETS_OF . ' WHERE ' . $where . ' GROUP BY t.status', $params) as $r) {
            $out[$r['status']] = (int) $r['n'];
        }
        return $out;
    }

    /**
     * The customer's tickets as a derived table joined by primary key: two
     * indexed lookups (main customer, added customer) instead of a scan of
     * all the tickets. Two placeholders: the customer id twice.
     */
    private const TICKETS_OF = '(SELECT id FROM tickets WHERE customer_id = ?
                                 UNION SELECT ticket_id FROM ticket_customers WHERE customer_id = ?) mine
                                JOIN tickets t ON t.id = mine.id';

    /** Conditions on the customer's tickets: not merged, optionally searched */
    private static function ticketWhere(int $customerId, string $q): array
    {
        $where = 't.merged_into IS NULL';
        $params = [$customerId, $customerId]; // for the two placeholders of TICKETS_OF
        $q = trim($q);
        if ($q !== '') {
            $num = ltrim($q, '#');
            $like = '%' . addcslashes($q, '%_\\') . '%';
            if ($num !== '' && ctype_digit($num)) {
                $where .= ' AND (t.number LIKE ? OR t.subject LIKE ?)';
                array_push($params, addcslashes($num, '%_\\') . '%', $like);
            } else {
                $where .= ' AND t.subject LIKE ?';
                $params[] = $like;
            }
        }
        return [$where, $params];
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
