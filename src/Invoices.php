<?php
declare(strict_types=1);

namespace Bhelpdesk;

/**
 * Invoices to issue (Billing -> Invoice).
 *
 * An invoice has a customer, a text, an amount, the day billing starts and a
 * recurrence (one-off, monthly, every two months, yearly). "Issued" switches it
 * from "to issue" to "issued". For recurring invoices the list shows the next
 * billing date computed from the start date.
 */
final class Invoices
{
    public const RECURRENCES = ['once', 'monthly', 'bimonthly', 'yearly'];
    private const MONTHS = ['monthly' => 1, 'bimonthly' => 2, 'yearly' => 12];

    private const SELECT = '
        SELECT i.*, c.name AS customer_name, c.company AS customer_company, c.email AS customer_email,
               o.name AS issued_by_name, cb.name AS created_by_name,
               t.number AS ticket_number, t.subject AS ticket_subject
          FROM invoices i
          JOIN customers c      ON c.id = i.customer_id
          LEFT JOIN tickets t   ON t.id = i.ticket_id
          LEFT JOIN operators o ON o.id = i.issued_by
          LEFT JOIN operators cb ON cb.id = i.created_by';

    public static function find(int $id): ?array
    {
        return Db::one(self::SELECT . ' WHERE i.id = ?', [$id]);
    }

    public static function forTicket(int $ticketId): array
    {
        return Db::all(self::SELECT . ' WHERE i.ticket_id = ? ORDER BY i.id', [$ticketId]);
    }

    public static function forCustomer(int $customerId): array
    {
        return Db::all(self::SELECT . ' WHERE i.customer_id = ? ORDER BY i.issued, i.start_date DESC, i.id DESC', [$customerId]);
    }

    /**
     * Filters: status ('todo', 'issued', 'all'), recurrence, q (customer name/company/email, text, id).
     * @return array{rows: list<array>, total: int, sum: float}
     */
    public static function search(array $f, int $page = 1, int $perPage = 30): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (($f['status'] ?? 'todo') === 'todo') {
            $where[] = 'i.issued = 0';
        } elseif (($f['status'] ?? '') === 'issued') {
            $where[] = 'i.issued = 1';
        }
        if (in_array($f['recurrence'] ?? '', self::RECURRENCES, true)) {
            $where[] = 'i.recurrence = ?';
            $params[] = $f['recurrence'];
        }
        if (($f['q'] ?? '') !== '') {
            $like = '%' . addcslashes((string) $f['q'], '%_\\') . '%';
            $where[] = '(c.name LIKE ? OR c.company LIKE ? OR c.email LIKE ? OR i.description LIKE ? OR i.id = ? OR i.ticket_id = (SELECT id FROM tickets WHERE number = ?))';
            array_push($params, $like, $like, $like, $like, (int) ltrim((string) $f['q'], '#'), ltrim((string) $f['q'], '#'));
        }
        $sqlWhere = implode(' AND ', $where);
        $agg = Db::one('SELECT COUNT(*) AS n, COALESCE(SUM(i.amount), 0) AS s FROM invoices i JOIN customers c ON c.id = i.customer_id LEFT JOIN tickets t ON t.id = i.ticket_id WHERE ' . $sqlWhere, $params);
        $rows = Db::all(self::SELECT . ' WHERE ' . $sqlWhere . ' ORDER BY i.issued, i.start_date, i.id LIMIT ' . $perPage . ' OFFSET ' . (max(0, $page - 1) * $perPage), $params);
        return ['rows' => $rows, 'total' => (int) $agg['n'], 'sum' => (float) $agg['s']];
    }

    /** @return array{values: array, errors: list<string>} */
    public static function validate(array $in): array
    {
        $v = [
            'customer_email' => mb_strtolower(trim((string) ($in['customer_email'] ?? ''))),
            'description'    => trim(str_replace("\r\n", "\n", (string) ($in['description'] ?? ''))),
            'amount_input'   => trim((string) ($in['amount'] ?? '')),
            'start_date'     => trim((string) ($in['start_date'] ?? '')),
            'recurrence'     => (string) ($in['recurrence'] ?? 'once'),
            'issued'         => !empty($in['issued']),
        ];
        $errors = [];
        $customer = $v['customer_email'] !== '' ? Customers::findByEmail($v['customer_email']) : null;
        $v['customer_id'] = $customer ? (int) $customer['id'] : 0;
        $v['customer_name'] = $customer ? (string) $customer['name'] : '';
        if (!$customer) {
            $errors[] = __('inv.err_customer');
        }
        if ($v['description'] === '' || mb_strlen($v['description']) > 5000) {
            $errors[] = __('inv.err_text');
        }
        $v['amount'] = self::parseAmount($v['amount_input']);
        if ($v['amount'] === null) {
            $errors[] = __('inv.err_amount');
        }
        $d = \DateTime::createFromFormat('!Y-m-d', $v['start_date']);
        if (!$d || $d->format('Y-m-d') !== $v['start_date']) {
            $errors[] = __('inv.err_date');
        }
        if (!in_array($v['recurrence'], self::RECURRENCES, true)) {
            $errors[] = __('inv.err_recurrence');
            $v['recurrence'] = 'once';
        }
        return ['values' => $v, 'errors' => $errors];
    }

    /** "1.234,56", "1234.56", "1,234.56", "€ 90" -> float with 2 decimals (null if not valid) */
    public static function parseAmount(string $s): ?float
    {
        $s = str_replace(['€', ' ', "\u{00A0}", "'"], '', $s);
        if ($s === '' || !preg_match('/^-?[\d.,]+$/', $s)) {
            return null;
        }
        $lastComma = strrpos($s, ',');
        $lastDot = strrpos($s, '.');
        if ($lastComma !== false && $lastDot !== false) {
            $dec = $lastComma > $lastDot ? ',' : '.';
            $s = str_replace($dec === ',' ? '.' : ',', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif ($lastComma !== false) {
            $s = substr_count($s, ',') === 1 ? str_replace(',', '.', $s) : str_replace(',', '', $s);
        } elseif ($lastDot !== false && (substr_count($s, '.') > 1 || strlen($s) - $lastDot - 1 === 3)) {
            $s = str_replace('.', '', $s); // 1.234 / 1.234.567 = thousands separators
        }
        if (!is_numeric($s)) {
            return null;
        }
        $n = round((float) $s, 2);
        return $n >= 0 && $n < 10000000000 ? $n : null;
    }

    /** $ticket: the ticket the invoice is created from (its history records it) */
    public static function create(array $v, array $op, ?array $ticket = null): int
    {
        $id = Db::insert(
            'INSERT INTO invoices (customer_id, ticket_id, description, amount, start_date, recurrence, issued, issued_at, issued_by, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ' . ($v['issued'] ? 'NOW()' : 'NULL') . ', ?, ?)',
            [$v['customer_id'], $ticket ? (int) $ticket['id'] : null, $v['description'], $v['amount'], $v['start_date'], $v['recurrence'], (int) $v['issued'], $v['issued'] ? (int) $op['id'] : null, (int) $op['id']]
        );
        if ($ticket) {
            Tickets::addMessage((int) $ticket['id'], 'system', (int) $op['id'], json_encode([
                'event' => 'invoice_added', 'by' => $op['name'], 'id' => $id, 'amount' => (float) $v['amount'], 'recurrence' => $v['recurrence'],
            ], JSON_UNESCAPED_UNICODE), true, null);
        }
        return $id;
    }

    public static function update(array $invoice, array $v, int $operatorId): void
    {
        Db::exec(
            'UPDATE invoices SET customer_id = ?, description = ?, amount = ?, start_date = ?, recurrence = ? WHERE id = ?',
            [$v['customer_id'], $v['description'], $v['amount'], $v['start_date'], $v['recurrence'], (int) $invoice['id']]
        );
        self::setIssued($invoice, $v['issued'], $operatorId);
    }

    /** Issued / to issue; records who and when */
    public static function setIssued(array $invoice, bool $issued, int $operatorId): void
    {
        if ($issued === (bool) $invoice['issued']) {
            return;
        }
        Db::exec(
            'UPDATE invoices SET issued = ?, issued_at = ' . ($issued ? 'NOW()' : 'NULL') . ', issued_by = ? WHERE id = ?',
            [(int) $issued, $issued ? $operatorId : null, (int) $invoice['id']]
        );
    }

    public static function delete(int $id): void
    {
        Db::exec('DELETE FROM invoices WHERE id = ?', [$id]);
    }

    /** Next billing date: the start date, or for recurring invoices the first period from today on */
    public static function nextDate(array $i): string
    {
        $start = (string) $i['start_date'];
        $months = self::MONTHS[$i['recurrence']] ?? 0;
        $today = date('Y-m-d');
        if ($months === 0 || $start >= $today) {
            return $start;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $start));
        $diff = ((int) date('Y') - $y) * 12 + ((int) date('n') - $m);
        $k = max(0, intdiv($diff, $months));
        for ($i2 = 0; $i2 < 3; $i2++, $k++) {
            $date = self::addMonths($y, $m, $d, $k * $months);
            if ($date >= $today) {
                return $date;
            }
        }
        return self::addMonths($y, $m, $d, $k * $months);
    }

    /** Add months keeping the day (31 Jan + 1 month = 28/29 Feb) */
    private static function addMonths(int $y, int $m, int $d, int $add): string
    {
        $total = $y * 12 + ($m - 1) + $add;
        $ny = intdiv($total, 12);
        $nm = $total % 12 + 1;
        $last = (int) date('t', mktime(0, 0, 0, $nm, 1, $ny));
        return sprintf('%04d-%02d-%02d', $ny, $nm, min($d, $last));
    }

    /** Amount in the current language format (e.g. 1.234,56 € / €1,234.56) */
    public static function money(float $n): string
    {
        return __('inv.money', ['n' => number_format($n, 2, __('inv.dec_point'), __('inv.thousands'))]);
    }

    /** Date (Y-m-d) in the current language format, without time */
    public static function day(string $ymd): string
    {
        $fmt = trim((string) preg_replace('/[ ,]*[HhGgis:]+.*$/', '', (string) __('date_format')));
        return date($fmt !== '' ? $fmt : 'd/m/Y', (int) strtotime($ymd));
    }
}
