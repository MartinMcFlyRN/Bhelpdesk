<?php
declare(strict_types=1);

namespace Bhelpdesk;

use RuntimeException;

final class Tickets
{
    public const STATUSES   = ['open', 'answered', 'on_hold', 'closed'];
    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    private const SELECT = '
        SELECT t.*, c.name AS customer_name, c.email AS customer_email, c.company AS customer_company, c.notes AS customer_notes,
               d.name AS department_name, o.name AS assignee_name
          FROM tickets t
          JOIN customers c        ON c.id = t.customer_id
          LEFT JOIN departments d ON d.id = t.department_id
          LEFT JOIN operators o   ON o.id = t.assigned_to';

    public static function departments(bool $onlyActive = true): array
    {
        return Db::all('SELECT id, name FROM departments' . ($onlyActive ? ' WHERE active = 1' : '') . ' ORDER BY sort_order, name');
    }

    /**
     * Active operators who can work on tickets of a department:
     * administrators plus agents assigned to that department.
     */
    public static function operators(?int $departmentId = null): array
    {
        return Db::all(
            "SELECT o.id, o.name, o.role FROM operators o
              WHERE o.active = 1
                AND (o.role = 'admin' OR EXISTS (SELECT 1 FROM operator_departments od WHERE od.operator_id = o.id AND od.department_id = ?))
              ORDER BY o.name",
            [(int) $departmentId]
        );
    }

    /**
     * SQL condition (on the tickets alias) limiting tickets to those $op may see:
     * administrators see everything; agents see the tickets of their departments
     * plus the tickets assigned to them.
     * @return array{0: string, 1: array}
     */
    public static function visibility(array $op, string $alias = 't'): array
    {
        if (($op['role'] ?? '') === 'admin') {
            return ['1 = 1', []];
        }
        $deps = Operators::departmentIds((int) $op['id']);
        $cond = $alias . '.assigned_to = ?';
        $params = [(int) $op['id']];
        if ($deps) {
            $cond = '(' . $alias . '.department_id IN (' . implode(',', array_fill(0, count($deps), '?')) . ') OR ' . $cond . ')';
            $params = array_merge($deps, $params);
        }
        return [$cond, $params];
    }

    public static function canSee(array $ticket, array $op): bool
    {
        if (($op['role'] ?? '') === 'admin' || (int) $ticket['assigned_to'] === (int) $op['id']) {
            return true;
        }
        return $ticket['department_id'] !== null
            && in_array((int) $ticket['department_id'], Operators::departmentIds((int) $op['id']), true);
    }

    /** May this operator be assigned a ticket of that department? */
    public static function canWorkOn(int $operatorId, ?int $departmentId): bool
    {
        $op = Db::one('SELECT id, role FROM operators WHERE id = ? AND active = 1', [$operatorId]);
        if (!$op) {
            return false;
        }
        return $op['role'] === 'admin'
            || ($departmentId && in_array($departmentId, Operators::departmentIds($operatorId), true));
    }

    /** @return array{number: string, link: string, customer_id: int} */
    public static function create(string $name, string $email, ?int $departmentId, string $subject, string $body, string $ip, string $language = 'en', array $files = [], ?string $phone = null): array
    {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $customerId = (int) Db::value('SELECT id FROM customers WHERE email = ?', [$email]);
            if ($customerId) {
                // existing customer: keep the registry data curated by operators,
                // only fill the phone if missing and remember the latest language
                Db::exec('UPDATE customers SET language = ?, phone = COALESCE(phone, ?) WHERE id = ?', [$language, $phone, $customerId]);
            } else {
                $customerId = Db::insert('INSERT INTO customers (name, email, phone, language) VALUES (?, ?, ?, ?)', [$name, $email, $phone, $language]);
            }

            $number = self::newNumber();
            $ticketId = Db::insert(
                'INSERT INTO tickets (number, customer_id, department_id, subject, phone, created_ip) VALUES (?, ?, ?, ?, ?, ?)',
                [$number, $customerId, $departmentId, $subject, $phone, $ip]
            );
            $messageId = self::addMessage($ticketId, 'customer', null, $body, false, $ip, $customerId);
            if ($files) {
                Attachments::store($ticketId, $messageId, $files);
            }
            $link = AccessLinks::issue($ticketId, $customerId);
            $pdo->commit();
        } catch (\Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
        return ['number' => $number, 'link' => $link, 'customer_id' => $customerId];
    }

    private static function newNumber(): string
    {
        for ($i = 0; $i < 20; $i++) {
            $n = (string) random_int(100000, 999999);
            if (!Db::value('SELECT 1 FROM tickets WHERE number = ?', [$n])) {
                return $n;
            }
        }
        throw new RuntimeException('Unable to generate a unique ticket number');
    }

    public static function find(int $id): ?array
    {
        return Db::one(self::SELECT . ' WHERE t.id = ?', [$id]);
    }

    public static function findByNumber(string $number): ?array
    {
        return Db::one(self::SELECT . ' WHERE t.number = ?', [$number]);
    }

    /** May this customer still access the ticket? (main or additional customer) */
    public static function customerHasAccess(array $ticket, int $customerId): bool
    {
        return $customerId === (int) $ticket['customer_id']
            || (bool) Db::value('SELECT 1 FROM ticket_customers WHERE ticket_id = ? AND customer_id = ?', [(int) $ticket['id'], $customerId]);
    }

    /** Additional customers of a ticket */
    public static function participants(int $ticketId): array
    {
        return Db::all(
            'SELECT c.id, c.name, c.email, c.company, tc.created_at AS added_at
               FROM ticket_customers tc JOIN customers c ON c.id = tc.customer_id
              WHERE tc.ticket_id = ?
              ORDER BY tc.created_at, c.name',
            [$ticketId]
        );
    }

    public static function addParticipant(array $ticket, array $op, int $customerId): bool
    {
        if ($customerId === (int) $ticket['customer_id']
            || Db::value('SELECT 1 FROM ticket_customers WHERE ticket_id = ? AND customer_id = ?', [(int) $ticket['id'], $customerId])) {
            return false;
        }
        Db::exec('INSERT INTO ticket_customers (ticket_id, customer_id, added_by) VALUES (?, ?, ?)', [(int) $ticket['id'], $customerId, (int) $op['id']]);
        self::logEvent((int) $ticket['id'], $op, ['event' => 'customer_added', 'name' => self::customerLabel($customerId)]);
        return true;
    }

    public static function removeParticipant(array $ticket, array $op, int $customerId): bool
    {
        $n = Db::exec('DELETE FROM ticket_customers WHERE ticket_id = ? AND customer_id = ?', [(int) $ticket['id'], $customerId]);
        if ($n) {
            AccessLinks::revoke((int) $ticket['id'], $customerId);
            self::logEvent((int) $ticket['id'], $op, ['event' => 'customer_removed', 'name' => self::customerLabel($customerId)]);
        }
        return $n > 0;
    }

    /**
     * Change the main customer. The previous one can stay on the ticket as additional customer.
     */
    public static function changeCustomer(array $ticket, array $op, int $newCustomerId, bool $keepPrevious): bool
    {
        $old = (int) $ticket['customer_id'];
        if ($newCustomerId === $old) {
            return false;
        }
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            Db::exec('UPDATE tickets SET customer_id = ?, updated_at = NOW() WHERE id = ?', [$newCustomerId, (int) $ticket['id']]);
            // the new main customer is no longer an "additional" one
            Db::exec('DELETE FROM ticket_customers WHERE ticket_id = ? AND customer_id = ?', [(int) $ticket['id'], $newCustomerId]);
            if ($keepPrevious) {
                Db::exec('INSERT IGNORE INTO ticket_customers (ticket_id, customer_id, added_by) VALUES (?, ?, ?)', [(int) $ticket['id'], $old, (int) $op['id']]);
            } else {
                // the previous main customer leaves the ticket: their links stop working
                AccessLinks::revoke((int) $ticket['id'], $old);
            }
            self::logEvent((int) $ticket['id'], $op, [
                'event' => 'customer_changed',
                'from'  => self::customerLabel($old),
                'to'    => self::customerLabel($newCustomerId),
                'kept'  => $keepPrevious,
            ]);
            $pdo->commit();
        } catch (\Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
        return true;
    }

    private static function customerLabel(int $customerId): string
    {
        $c = Db::one('SELECT name, email FROM customers WHERE id = ?', [$customerId]);
        return $c ? $c['name'] . ' <' . $c['email'] . '>' : '#' . $customerId;
    }

    private static function logEvent(int $ticketId, array $op, array $event): void
    {
        self::addMessage($ticketId, 'system', (int) $op['id'], json_encode($event + ['by' => $op['name']], JSON_UNESCAPED_UNICODE), true, null);
    }

    public static function messages(int $ticketId, bool $includeInternal): array
    {
        return Db::all(
            'SELECT m.*, o.name AS operator_name, mc.name AS customer_name, mc.email AS customer_email
               FROM ticket_messages m
               LEFT JOIN operators o ON o.id = m.operator_id
               LEFT JOIN customers mc ON mc.id = m.customer_id
              WHERE m.ticket_id = ?' . ($includeInternal ? '' : ' AND m.is_internal = 0') . '
              ORDER BY m.created_at, m.id',
            [$ticketId]
        );
    }

    public static function addMessage(int $ticketId, string $authorType, ?int $operatorId, string $body, bool $internal, ?string $ip, ?int $customerId = null, string $format = 'text'): int
    {
        $format = $format === 'html' ? 'html' : 'text';
        $id = Db::insert(
            'INSERT INTO ticket_messages (ticket_id, author_type, operator_id, customer_id, body, body_format, is_internal, ip) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$ticketId, $authorType, $operatorId, $customerId, $format === 'html' ? Html::sanitize($body) : $body, $format, (int) $internal, $ip]
        );
        Db::exec('UPDATE tickets SET updated_at = NOW() WHERE id = ?', [$ticketId]);
        return $id;
    }

    public static function customerReply(array $ticket, string $body, string $ip, ?int $customerId = null): void
    {
        self::addMessage((int) $ticket['id'], 'customer', null, $body, false, $ip, $customerId ?: (int) $ticket['customer_id']);
        if ($ticket['status'] !== 'open') {
            self::setStatus((int) $ticket['id'], 'open');
            if ($ticket['status'] === 'closed') {
                self::addMessage((int) $ticket['id'], 'system', null, json_encode(['event' => 'reopened']), true, null);
            }
        }
    }

    /**
     * Operator reply or internal note; $format 'html' for the WYSIWYG editor,
     * $files = validated uploads (Attachments::fromUpload with the 'operator' profile).
     */
    public static function operatorReply(array $ticket, array $op, string $body, bool $internal, string $newStatus, string $ip, string $format = 'text', array $files = []): void
    {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $messageId = self::addMessage((int) $ticket['id'], 'operator', (int) $op['id'], $body, $internal, $ip, null, $format);
            if ($files) {
                Attachments::store((int) $ticket['id'], $messageId, $files);
            }
            $pdo->commit();
        } catch (\Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
        if (in_array($newStatus, self::STATUSES, true) && $newStatus !== $ticket['status']) {
            self::update($ticket, $op, ['status' => $newStatus]);
        }
    }

    private static function setStatus(int $id, string $status): void
    {
        Db::exec(
            'UPDATE tickets SET status = ?, closed_at = ' . ($status === 'closed' ? 'NOW()' : 'NULL') . ', updated_at = NOW() WHERE id = ?',
            [$status, $id]
        );
    }

    /**
     * Update status / priority / department / assignee, logging every
     * change as an internal system event (translated when displayed).
     */
    public static function update(array $ticket, array $op, array $changes): void
    {
        $id = (int) $ticket['id'];
        $log = [];

        if (isset($changes['status']) && in_array($changes['status'], self::STATUSES, true) && $changes['status'] !== $ticket['status']) {
            self::setStatus($id, $changes['status']);
            $log[] = ['status', $ticket['status'], $changes['status']];
        }
        if (isset($changes['priority']) && in_array($changes['priority'], self::PRIORITIES, true) && $changes['priority'] !== $ticket['priority']) {
            Db::exec('UPDATE tickets SET priority = ? WHERE id = ?', [$changes['priority'], $id]);
            $log[] = ['priority', $ticket['priority'], $changes['priority']];
        }
        if (array_key_exists('department_id', $changes)) {
            $dep = $changes['department_id'] ?: null;
            if ($dep !== null && !Db::value('SELECT 1 FROM departments WHERE id = ?', [$dep])) {
                $dep = (int) $ticket['department_id'] ?: null;
            }
            if ((int) $dep !== (int) $ticket['department_id']) {
                Db::exec('UPDATE tickets SET department_id = ? WHERE id = ?', [$dep, $id]);
                $name = $dep ? (string) Db::value('SELECT name FROM departments WHERE id = ?', [$dep]) : null;
                $log[] = ['department', $name];
            }
        }
        if (array_key_exists('assigned_to', $changes)) {
            $to = $changes['assigned_to'] ?: null;
            // the assignee must be able to work on the ticket's (possibly new) department
            $depNow = (int) Db::value('SELECT department_id FROM tickets WHERE id = ?', [$id]) ?: null;
            if ($to !== null && (int) $to !== (int) $ticket['assigned_to'] && !self::canWorkOn((int) $to, $depNow)) {
                $to = (int) $ticket['assigned_to'] ?: null;
            }
            if ((int) $to !== (int) $ticket['assigned_to']) {
                Db::exec('UPDATE tickets SET assigned_to = ? WHERE id = ?', [$to, $id]);
                $name = $to ? (string) Db::value('SELECT name FROM operators WHERE id = ?', [$to]) : null;
                $log[] = ['assigned', $name];
            }
        }

        if ($log) {
            self::addMessage($id, 'system', (int) $op['id'], json_encode(['event' => 'changed', 'by' => $op['name'], 'changes' => $log], JSON_UNESCAPED_UNICODE), true, null);
        }
    }

    public static function countsByStatus(array $op): array
    {
        [$vis, $vp] = self::visibility($op);
        $rows = Db::all('SELECT t.status, COUNT(*) n FROM tickets t WHERE ' . $vis . ' GROUP BY t.status', $vp);
        $out = array_fill_keys(self::STATUSES, 0);
        foreach ($rows as $r) {
            $out[$r['status']] = (int) $r['n'];
        }
        return $out;
    }

    /**
     * Search for the operators dashboard.
     * @return array{rows: array, total: int}
     */
    public static function search(array $f, array $op, int $page = 1, int $perPage = 25): array
    {
        $opId = (int) $op['id'];
        [$vis, $vp] = self::visibility($op);
        $where = [$vis];
        $p = $vp;

        if (!empty($f['status']) && in_array($f['status'], self::STATUSES, true)) {
            $where[] = 't.status = ?';
            $p[] = $f['status'];
        } elseif (($f['status'] ?? '') !== 'all') {
            $where[] = "t.status <> 'closed'";
        }
        if (($f['assigned'] ?? '') === 'me') {
            $where[] = 't.assigned_to = ?';
            $p[] = $opId;
        } elseif (($f['assigned'] ?? '') === 'none') {
            $where[] = 't.assigned_to IS NULL';
        }
        if (!empty($f['department'])) {
            $where[] = 't.department_id = ?';
            $p[] = (int) $f['department'];
        }
        if (!empty($f['q'])) {
            $where[] = '(t.number = ? OR t.subject LIKE ? OR c.email LIKE ? OR c.name LIKE ? OR t.phone LIKE ?)';
            $like = '%' . addcslashes((string) $f['q'], '%_\\') . '%';
            array_push($p, $f['q'], $like, $like, $like, $like);
        }

        $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $total = (int) Db::value('SELECT COUNT(*) FROM tickets t JOIN customers c ON c.id = t.customer_id' . $sqlWhere, $p);

        $offset = max(0, ($page - 1) * $perPage);
        $rows = Db::all(
            self::SELECT . $sqlWhere . "
             ORDER BY FIELD(t.priority, 'urgent', 'high', 'normal', 'low'), t.updated_at DESC
             LIMIT $perPage OFFSET $offset",
            $p
        );
        return ['rows' => $rows, 'total' => $total];
    }
}
