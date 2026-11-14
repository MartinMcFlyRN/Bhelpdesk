<?php
declare(strict_types=1);

namespace Bhelpdesk;

/**
 * Email alerts to operators.
 *
 *  - new ticket       -> operators of the ticket's department (administrators: all departments)
 *  - moved ticket     -> operators of the new department who could not see it before
 *  - customer reply   -> the assignee; when unassigned, the operators of the department
 *  - assignment       -> the operator the ticket was assigned to
 *
 * Each operator chooses the alerts in their profile (alert_new / alert_reply /
 * alert_assign); texts are the operator_* templates of /op/config/templates. The operator who caused the event
 * gets nothing. Alerts are queued and sent after the page has been delivered
 * (fastcgi_finish_request), so nobody waits for the SMTP server.
 */
final class Alerts
{
    private const EXCERPT = 1500;

    /** @var list<array{0: string, 1: array}> */
    private static array $queue = [];
    private static bool $registered = false;

    /** @param string $source 'web', 'email' or the name of the operator who opened it */
    public static function newTicket(int $ticketId, ?int $actorId, string $source): void
    {
        self::enqueue('new', ['ticket' => $ticketId, 'actor' => $actorId, 'source' => $source]);
    }

    public static function customerReply(int $ticketId, int $messageId): void
    {
        self::enqueue('reply', ['ticket' => $ticketId, 'message' => $messageId]);
    }

    public static function assigned(int $ticketId, int $operatorId, array $by): void
    {
        self::enqueue('assigned', ['ticket' => $ticketId, 'operator' => $operatorId, 'actor' => (int) $by['id'], 'by' => (string) $by['name']]);
    }

    public static function transferred(int $ticketId, ?int $oldDepartmentId, array $by): void
    {
        self::enqueue('transfer', ['ticket' => $ticketId, 'old' => $oldDepartmentId, 'actor' => (int) $by['id'], 'by' => (string) $by['name']]);
    }

    private static function enqueue(string $kind, array $data): void
    {
        if (!config('mail.alerts', true)) {
            return;
        }
        self::$queue[] = [$kind, $data];
        if (!self::$registered) {
            self::$registered = true;
            register_shutdown_function([self::class, 'flush']);
        }
    }

    /** Send the queued alerts (called at shutdown; can be called directly, e.g. by tests) */
    public static function flush(): void
    {
        if (!self::$queue) {
            return;
        }
        if (PHP_SAPI !== 'cli' && function_exists('fastcgi_finish_request')) {
            @session_write_close();
            fastcgi_finish_request(); // the browser already has the page
        }
        $queue = self::$queue;
        self::$queue = [];
        foreach ($queue as [$kind, $data]) {
            try {
                self::process($kind, $data);
            } catch (\Throwable $ex) {
                error_log('Bhelpdesk: alert ' . $kind . ' for ticket ' . ($data['ticket'] ?? '?') . ' failed: ' . $ex->getMessage());
            }
        }
    }

    private static function process(string $kind, array $d): void
    {
        $ticket = Tickets::find((int) $d['ticket']);
        if (!$ticket) {
            return;
        }
        $dep = $ticket['department_id'] !== null ? (int) $ticket['department_id'] : null;
        $actor = (int) ($d['actor'] ?? 0);

        switch ($kind) {
            case 'new':
                $to = self::departmentOperators($dep, 'alert_new');
                $excerptMsg = (int) Db::value("SELECT id FROM ticket_messages WHERE ticket_id = ? AND author_type = 'customer' ORDER BY id LIMIT 1", [(int) $ticket['id']]);
                break;
            case 'transfer':
                $before = array_column(self::departmentOperators($d['old'] !== null ? (int) $d['old'] : null, null), 'id');
                $to = array_values(array_filter(self::departmentOperators($dep, 'alert_new'), static fn (array $o): bool => !in_array($o['id'], $before, true)));
                $excerptMsg = self::lastCustomerMessage((int) $ticket['id']);
                break;
            case 'reply':
                $to = $ticket['assigned_to'] !== null
                    ? self::operators('id = ? AND alert_reply = 1', [(int) $ticket['assigned_to']])
                    : self::departmentOperators($dep, 'alert_reply');
                $excerptMsg = (int) $d['message'];
                break;
            case 'assigned':
                $to = self::operators('id = ? AND alert_assign = 1', [(int) $d['operator']]);
                $excerptMsg = self::lastCustomerMessage((int) $ticket['id']);
                break;
            default:
                return;
        }
        $msg = $excerptMsg ? Db::one('SELECT m.*, c.name AS customer_name FROM ticket_messages m LEFT JOIN customers c ON c.id = m.customer_id WHERE m.id = ?', [$excerptMsg]) : null;
        foreach ($to as $op) {
            if ((int) $op['id'] === $actor) {
                continue;
            }
            self::send($kind, $ticket, $op, $msg, $d);
        }
    }

    /** Active operators who see tickets of a department (admins: all), optionally with an alert enabled */
    private static function departmentOperators(?int $departmentId, ?string $flag): array
    {
        $cond = $flag ? ' AND ' . $flag . ' = 1' : '';
        if ($departmentId === null) {
            return self::operators("role = 'admin'" . $cond, []);
        }
        return self::operators(
            "(role = 'admin' OR id IN (SELECT operator_id FROM operator_departments WHERE department_id = ?))" . $cond,
            [$departmentId]
        );
    }

    private static function operators(string $where, array $params): array
    {
        return array_map(
            static fn (array $o): array => ['id' => (int) $o['id']] + $o,
            Db::all('SELECT id, name, email FROM operators WHERE active = 1 AND ' . $where . ' ORDER BY id', $params)
        );
    }

    private static function lastCustomerMessage(int $ticketId): int
    {
        return (int) Db::value("SELECT id FROM ticket_messages WHERE ticket_id = ? AND author_type = 'customer' ORDER BY id DESC LIMIT 1", [$ticketId]);
    }

    private static function send(string $kind, array $ticket, array $op, ?array $msg, array $d): void
    {
        $code = ['new' => 'operator_new', 'reply' => 'operator_reply', 'assigned' => 'operator_assigned', 'transfer' => 'operator_transfer'][$kind];
        $base = rtrim((string) config('base_url'), '/');
        $excerpt = '';
        if ($msg) {
            $text = $msg['body_format'] === 'html' ? Html::toText((string) $msg['body']) : (string) $msg['body'];
            $text = mb_strlen($text) > self::EXCERPT ? mb_substr($text, 0, self::EXCERPT) . ' […]' : $text;
            $excerpt = EmailTemplates::excerptHtml($text, (int) Db::value('SELECT COUNT(*) FROM attachments WHERE message_id = ?', [(int) $msg['id']]));
        }
        $mail = Notifier::defaultLanguage(static fn (): array => EmailTemplates::render($code, [
            'operator_name'   => (string) $op['name'],
            'ticket_number'   => (string) $ticket['number'],
            'ticket_subject'  => (string) $ticket['subject'],
            'customer_name'   => (string) ($msg['customer_name'] ?? $ticket['customer_name']),
            'customer_email'  => (string) $ticket['customer_email'],
            'department_name' => (string) ($ticket['department_name'] ?? '—'),
            'priority'        => priority_label((string) $ticket['priority']),
            'ticket_status'   => status_label((string) $ticket['status']),
            'assignee'        => (string) ($ticket['assignee_name'] ?? '—'),
            'by'              => (string) ($d['by'] ?? ''),
            'source'          => match ($d['source'] ?? '') { 'web' => 'web', 'email' => 'e-mail', default => (string) ($d['source'] ?? '') },
            'excerpt'         => $excerpt,
            'ticket_url'      => $base . '/op/ticket/' . (int) $ticket['id'],
            'profile_url'     => $base . '/op/profile',
            'app_name'        => (string) config('app_name', 'Bhelpdesk'),
        ]));
        $identity = MailIdentities::forDepartment($ticket['department_id'] !== null ? (int) $ticket['department_id'] : null);
        $ok = Mailer::send((string) $op['email'], $mail['subject'], $mail['text'], $identity, [
            'html'    => $mail['html'],
            'auto'    => 'auto-generated',
            'headers' => ['X-Bhelpdesk-Ticket' => (string) $ticket['number'], 'X-Bhelpdesk-Alert' => $kind],
        ]);
        Db::exec(
            "INSERT INTO mail_messages (direction, identity_id, message_id, ticket_id, address, subject, status, note) VALUES ('out', ?, ?, ?, ?, ?, ?, ?)",
            [$identity['id'] ?? null, Mailer::lastMessageId(), (int) $ticket['id'], mb_substr((string) $op['email'], 0, 190), mb_substr($mail['subject'], 0, 255), $ok ? 'alert' : 'error', $ok ? null : mb_substr((string) Mailer::lastError(), 0, 255)]
        );
    }
}
