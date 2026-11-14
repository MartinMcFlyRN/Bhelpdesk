<?php
declare(strict_types=1);

namespace Bhelpdesk;

/**
 * Quotes (Billing -> Quote): customer, text and amount, optionally created
 * from a ticket. Same behaviour as invoices, without issued state, billing
 * date and recurrence.
 */
final class Quotes
{
    private const SELECT = '
        SELECT q.*, c.name AS customer_name, c.company AS customer_company, c.email AS customer_email,
               cb.name AS created_by_name, t.number AS ticket_number, t.subject AS ticket_subject
          FROM quotes q
          JOIN customers c       ON c.id = q.customer_id
          LEFT JOIN operators cb ON cb.id = q.created_by
          LEFT JOIN tickets t    ON t.id = q.ticket_id';

    public static function find(int $id): ?array
    {
        return Db::one(self::SELECT . ' WHERE q.id = ?', [$id]);
    }

    public static function forTicket(int $ticketId): array
    {
        return Db::all(self::SELECT . ' WHERE q.ticket_id = ? ORDER BY q.id', [$ticketId]);
    }

    public static function forCustomer(int $customerId): array
    {
        return Db::all(self::SELECT . ' WHERE q.customer_id = ? ORDER BY q.id DESC', [$customerId]);
    }

    /**
     * Filter q: customer name/company/email, text, ID or ticket number.
     * @return array{rows: list<array>, total: int, sum: float}
     */
    public static function search(array $f, int $page = 1, int $perPage = 30): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (($f['q'] ?? '') !== '') {
            $like = '%' . addcslashes((string) $f['q'], '%_\\') . '%';
            $plain = ltrim((string) $f['q'], '#');
            $where[] = '(c.name LIKE ? OR c.company LIKE ? OR c.email LIKE ? OR q.description LIKE ? OR q.id = ? OR t.number = ?)';
            array_push($params, $like, $like, $like, $like, (int) $plain, $plain);
        }
        $sqlWhere = implode(' AND ', $where);
        $agg = Db::one('SELECT COUNT(*) AS n, COALESCE(SUM(q.amount), 0) AS s FROM quotes q JOIN customers c ON c.id = q.customer_id LEFT JOIN tickets t ON t.id = q.ticket_id WHERE ' . $sqlWhere, $params);
        $rows = Db::all(self::SELECT . ' WHERE ' . $sqlWhere . ' ORDER BY q.id DESC LIMIT ' . $perPage . ' OFFSET ' . (max(0, $page - 1) * $perPage), $params);
        return ['rows' => $rows, 'total' => (int) $agg['n'], 'sum' => (float) $agg['s']];
    }

    /** @return array{values: array, errors: list<string>} */
    public static function validate(array $in): array
    {
        $v = [
            'customer_email' => mb_strtolower(trim((string) ($in['customer_email'] ?? ''))),
            'description'    => trim(str_replace("\r\n", "\n", (string) ($in['description'] ?? ''))),
            'amount_input'   => trim((string) ($in['amount'] ?? '')),
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
        $v['amount'] = Invoices::parseAmount($v['amount_input']);
        if ($v['amount'] === null) {
            $errors[] = __('inv.err_amount');
        }
        return ['values' => $v, 'errors' => $errors];
    }

    /** $ticket: the ticket the quote is created from (its history records it) */
    public static function create(array $v, array $op, ?array $ticket = null): int
    {
        $id = Db::insert(
            'INSERT INTO quotes (customer_id, ticket_id, description, amount, created_by) VALUES (?, ?, ?, ?, ?)',
            [$v['customer_id'], $ticket ? (int) $ticket['id'] : null, $v['description'], $v['amount'], (int) $op['id']]
        );
        if ($ticket) {
            Tickets::addMessage((int) $ticket['id'], 'system', (int) $op['id'], json_encode([
                'event' => 'quote_added', 'by' => $op['name'], 'id' => $id, 'amount' => (float) $v['amount'],
            ], JSON_UNESCAPED_UNICODE), true, null);
        }
        return $id;
    }

    public static function update(array $quote, array $v): void
    {
        Db::exec(
            'UPDATE quotes SET customer_id = ?, description = ?, amount = ? WHERE id = ?',
            [$v['customer_id'], $v['description'], $v['amount'], (int) $quote['id']]
        );
    }

    public static function delete(int $id): void
    {
        Db::exec('DELETE FROM quotes WHERE id = ?', [$id]);
    }
}
