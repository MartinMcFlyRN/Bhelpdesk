<?php
declare(strict_types=1);

namespace Bhelpdesk;

/**
 * Slack notifications, configured per department in /op/config/general:
 * every new ticket and every customer reply of the department is posted to
 * the department's incoming webhook, like the osTicket Slack plugin:
 *
 *   Nuovo Ticket #668680 creato            (green bar)
 *   Ticket #402646 aggiornato              (amber bar, customer reply)
 *   <subject, linked to the ticket>
 *   Name (email) in *Department*
 *   ```first part of the message```
 *
 * Messages are queued and posted after the page has been delivered.
 */
final class Slack
{
    private const EXCERPT = 2500;
    private const COLOR_NEW = '#2eb67d';
    private const COLOR_REPLY = '#daa038';

    private static array $queue = [];
    private static bool $registered = false;

    public static function newTicket(int $ticketId): void
    {
        self::enqueue('new', $ticketId, null);
    }

    public static function customerReply(int $ticketId, int $messageId): void
    {
        self::enqueue('reply', $ticketId, $messageId);
    }

    /** Settings of every department: [department_id => row + 'configured' => bool] */
    public static function settings(): array
    {
        $out = [];
        foreach (Db::all('SELECT d.id, d.name, d.active, s.webhook, s.notify_new, s.notify_reply, s.last_at, s.last_error
                            FROM departments d LEFT JOIN department_slack s ON s.department_id = d.id ORDER BY d.name') as $r) {
            $r['configured'] = (string) ($r['webhook'] ?? '') !== '';
            $r['notify_new'] = $r['notify_new'] === null ? 1 : (int) $r['notify_new'];
            $r['notify_reply'] = $r['notify_reply'] === null ? 1 : (int) $r['notify_reply'];
            unset($r['webhook']);
            $out[(int) $r['id']] = $r;
        }
        return $out;
    }

    /**
     * Saves the form: $in[department_id] = ['webhook' => '', 'new' => 0|1, 'reply' => 0|1, 'remove' => 0|1].
     * An empty webhook keeps the saved one. Returns the list of errors (nothing is saved if any).
     */
    public static function save(array $in): array
    {
        $deps = self::settings();
        $rows = [];
        $errors = [];
        foreach ($deps as $id => $dep) {
            $v = (array) ($in[$id] ?? []);
            $url = trim((string) ($v['webhook'] ?? ''));
            if ($url !== '' && !self::validUrl($url)) {
                $errors[] = __('slack.err_url', ['dep' => $dep['name']]);
                continue;
            }
            $rows[$id] = ['url' => $url, 'remove' => !empty($v['remove']), 'new' => !empty($v['new']) ? 1 : 0, 'reply' => !empty($v['reply']) ? 1 : 0];
        }
        if ($errors) {
            return $errors;
        }
        foreach ($rows as $id => $r) {
            if ($r['remove']) {
                Db::exec('DELETE FROM department_slack WHERE department_id = ?', [$id]);
                continue;
            }
            if ($r['url'] !== '') {
                Db::exec('INSERT INTO department_slack (department_id, webhook, notify_new, notify_reply) VALUES (?, ?, ?, ?)
                          ON DUPLICATE KEY UPDATE webhook = VALUES(webhook), notify_new = VALUES(notify_new), notify_reply = VALUES(notify_reply), last_error = NULL',
                    [$id, Crypto::encrypt($r['url']), $r['new'], $r['reply']]);
            } else {
                Db::exec('UPDATE department_slack SET notify_new = ?, notify_reply = ? WHERE department_id = ?', [$r['new'], $r['reply'], $id]);
            }
        }
        return [];
    }

    public static function validUrl(string $url): bool
    {
        return (bool) preg_match('#^https://[a-z0-9.-]+(:\d+)?/\S+$#i', $url) && strlen($url) <= 500;
    }

    /** Posts a test message to a department's webhook; returns null or the error */
    public static function test(int $departmentId): ?string
    {
        $url = self::webhook($departmentId, null);
        if ($url === null) {
            return __('slack.not_configured');
        }
        $payload = Notifier::defaultLanguage(static fn (): array => [
            'text' => self::esc(__('slack.test_text', ['app' => (string) config('app_name', 'Bhelpdesk')])),
        ]);
        return self::post($departmentId, $url, $payload);
    }

    private static function enqueue(string $kind, int $ticketId, ?int $messageId): void
    {
        self::$queue[] = [$kind, $ticketId, $messageId];
        if (!self::$registered) {
            self::$registered = true;
            register_shutdown_function([self::class, 'flush']);
        }
    }

    public static function flush(): void
    {
        if (!self::$queue) {
            return;
        }
        if (PHP_SAPI !== 'cli' && function_exists('fastcgi_finish_request')) {
            @session_write_close();
            fastcgi_finish_request();
        }
        $queue = self::$queue;
        self::$queue = [];
        foreach ($queue as [$kind, $ticketId, $messageId]) {
            try {
                self::process($kind, $ticketId, $messageId);
            } catch (\Throwable $ex) {
                error_log('Bhelpdesk: Slack ' . $kind . ' for ticket ' . $ticketId . ' failed: ' . $ex->getMessage());
            }
        }
    }

    private static function process(string $kind, int $ticketId, ?int $messageId): void
    {
        $ticket = Tickets::find($ticketId);
        if (!$ticket || $ticket['department_id'] === null) {
            return;
        }
        $dep = (int) $ticket['department_id'];
        $url = self::webhook($dep, $kind);
        if ($url === null) {
            return;
        }
        $sql = 'SELECT m.*, c.name AS customer_name, c.email AS customer_email, o.name AS operator_name, o.email AS operator_email
                  FROM ticket_messages m
                  LEFT JOIN customers c ON c.id = m.customer_id
                  LEFT JOIN operators o ON o.id = m.operator_id';
        $msg = $kind === 'reply'
            ? Db::one($sql . ' WHERE m.id = ? AND m.ticket_id = ?', [(int) $messageId, $ticketId])
            : Db::one($sql . " WHERE m.ticket_id = ? AND m.is_internal = 0 AND m.author_type <> 'system' ORDER BY (m.author_type = 'customer') DESC, m.id LIMIT 1", [$ticketId]);
        self::post($dep, $url, self::payload($kind, $ticket, $msg));
    }

    /** Message in the format of the osTicket Slack plugin */
    public static function payload(string $kind, array $ticket, ?array $msg): array
    {
        $base = rtrim((string) config('base_url'), '/');
        $link = '<' . $base . '/op/ticket/' . (int) $ticket['id'] . '|#' . self::esc((string) $ticket['number']) . '>';
        $name = (string) ($msg['customer_name'] ?? $msg['operator_name'] ?? $ticket['customer_name']);
        $email = (string) ($msg['customer_email'] ?? $msg['operator_email'] ?? $ticket['customer_email']);
        $text = '';
        if ($msg) {
            $text = $msg['body_format'] === 'html' ? Html::toText((string) $msg['body']) : (string) $msg['body'];
            $text = trim(preg_replace("/\n{3,}/", "\n\n", str_replace("\r", '', $text)));
            if (mb_strlen($text) > self::EXCERPT) {
                $text = mb_substr($text, 0, self::EXCERPT) . ' […]';
            }
        }
        return Notifier::defaultLanguage(static function () use ($kind, $ticket, $link, $name, $email, $text, $base): array {
            $body = __('slack.who', ['name' => self::esc($name), 'email' => self::esc($email), 'department' => '*' . self::esc((string) $ticket['department_name']) . '*']);
            if ($text !== '') {
                // ``` inside the message would close the code block
                $body .= "\n```" . self::esc(str_replace('```', "'''", $text)) . '```';
            }
            return [
                'text'        => __($kind === 'new' ? 'slack.new_ticket' : 'slack.ticket_updated', ['link' => $link]),
                'attachments' => [[
                    'color'      => $kind === 'new' ? self::COLOR_NEW : self::COLOR_REPLY,
                    'title'      => (string) $ticket['subject'],
                    'title_link' => $base . '/op/ticket/' . (int) $ticket['id'],
                    'text'       => $body,
                    'mrkdwn_in'  => ['text'],
                    'footer'     => __('slack.footer', ['app' => (string) config('app_name', 'Bhelpdesk')]),
                    'ts'         => time(),
                ]],
            ];
        });
    }

    /** Decrypted webhook of a department, if enabled for this kind of event ($kind null: any) */
    private static function webhook(int $departmentId, ?string $kind): ?string
    {
        $r = Db::one('SELECT webhook, notify_new, notify_reply FROM department_slack WHERE department_id = ?', [$departmentId]);
        if (!$r || (string) $r['webhook'] === '') {
            return null;
        }
        if (($kind === 'new' && !(int) $r['notify_new']) || ($kind === 'reply' && !(int) $r['notify_reply'])) {
            return null;
        }
        $url = Crypto::decrypt((string) $r['webhook']);
        return $url !== '' ? $url : null;
    }

    /** POST to the webhook; records the outcome on the department; returns null or the error */
    private static function post(int $departmentId, string $url, array $payload): ?string
    {
        $error = null;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json; charset=utf-8'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
        ]);
        $res = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($res === false) {
            $error = curl_error($ch);
        } elseif ($code < 200 || $code >= 300) {
            $error = 'HTTP ' . $code . ': ' . mb_substr(trim((string) $res), 0, 150);
        }
        curl_close($ch);
        Db::exec('UPDATE department_slack SET last_at = NOW(), last_error = ? WHERE department_id = ?', [$error !== null ? mb_substr($error, 0, 255) : null, $departmentId]);
        if ($error !== null) {
            error_log('Bhelpdesk: Slack webhook of department ' . $departmentId . ': ' . $error);
        }
        return $error;
    }

    /** Escaping required by Slack in text: & < > */
    private static function esc(string $s): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);
    }
}
