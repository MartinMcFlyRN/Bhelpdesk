<?php
declare(strict_types=1);

namespace Bhelpdesk;

/**
 * Email notifications to customers, sent with the department's sending identity
 * and in each customer's language:
 *  - ticketOpened():  confirmation of a new ticket (web form, email, operator)
 *  - operatorReply(): the operator's reply, with department signature and attachments
 *
 * Each customer gets their own message with their own direct link. Messages carry
 * "[#number]" in the subject, a "reply above this line" marker and Message-ID /
 * In-Reply-To / References headers, so replies by email go back to the ticket.
 * Every message is recorded in mail_messages (direction 'out').
 */
final class Notifier
{
    private const MAX_ATTACH_BYTES = 10485760; // larger attachments are listed, not attached

    /**
     * @param list<int>|null $customerIds recipients (null = main customer only)
     * @return list<string> errors ("email: reason")
     */
    public static function ticketOpened(int $ticketId, ?array $customerIds = null, ?string $inReplyTo = null): array
    {
        if (!config('mail.notify_new', true)) {
            return [];
        }
        $ticket = Tickets::find($ticketId);
        if (!$ticket) {
            return [];
        }
        $errors = [];
        foreach (self::customers($ticket, $customerIds ?? [(int) $ticket['customer_id']]) as $c) {
            $err = Lang::with((string) $c['language'], static function () use ($ticket, $c, $inReplyTo): ?string {
                $url = AccessLinks::issue((int) $ticket['id'], (int) $c['id']);
                $p = self::params($ticket, $c) + ['url' => $url, 'days' => AccessLinks::days()];
                $text = __('mail.opened.marker') . "\n\n" . __('mail.opened.body', $p);
                return self::deliver($ticket, $c, __('mail.subject', $p), $text, '', [], 'auto-replied', $inReplyTo);
            });
            if ($err !== null) {
                $errors[] = $c['email'] . ': ' . $err;
            }
        }
        return $errors;
    }

    /** @return list<string> errors */
    public static function operatorReply(int $ticketId, int $messageId): array
    {
        if (!config('mail.notify_reply', true)) {
            return [];
        }
        $ticket = Tickets::find($ticketId);
        $msg = Db::one('SELECT m.*, o.name AS operator_name, o.email AS operator_email FROM ticket_messages m LEFT JOIN operators o ON o.id = m.operator_id WHERE m.id = ? AND m.ticket_id = ?', [$messageId, $ticketId]);
        if (!$ticket || !$msg || (int) $msg['is_internal'] === 1) {
            return [];
        }
        $op = ['name' => (string) $msg['operator_name'], 'email' => (string) $msg['operator_email']];
        $bodyHtml = self::absoluteUrls($msg['body_format'] === 'html' ? Html::sanitize((string) $msg['body']) : Html::fromText((string) $msg['body']));
        $files = Db::all('SELECT original_name, stored_name, mime, size FROM attachments WHERE message_id = ? ORDER BY id', [$messageId]);

        // attach files up to 10 MB in total, list the others
        $attach = [];
        $listed = [];
        $total = 0;
        foreach ($files as $f) {
            if ($total + (int) $f['size'] <= self::MAX_ATTACH_BYTES && is_file(Attachments::path($f))) {
                $attach[] = [$f['original_name'], $f['mime'], Attachments::path($f)];
                $total += (int) $f['size'];
            } else {
                $listed[] = $f['original_name'] . ' (' . Attachments::humanSize((int) $f['size']) . ')';
            }
        }

        $errors = [];
        foreach (self::customers($ticket, null) as $c) {
            $err = Lang::with((string) $c['language'], static function () use ($ticket, $c, $op, $bodyHtml, $attach, $listed): ?string {
                $url = AccessLinks::issue((int) $ticket['id'], (int) $c['id']);
                $p = self::params($ticket, $c) + ['url' => $url, 'days' => AccessLinks::days(), 'status' => status_label((string) $ticket['status'])];
                $sig = self::absoluteUrls(Canned::signature($ticket, $op));
                $html = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#1f2937">'
                    . '<p style="color:#9ca3af;font-size:12px;margin:0 0 16px">' . e(__('mail.reply.marker')) . '</p>'
                    . $bodyHtml
                    . ($sig !== '' ? '<div style="margin-top:16px">' . $sig . '</div>' : '')
                    . ($listed ? '<p style="font-size:13px;color:#4b5563">' . e(__('mail.reply.files_on_ticket')) . '<br>' . implode('<br>', array_map('e', $listed)) . '</p>' : '')
                    . '<hr style="border:0;border-top:1px solid #e5e7eb;margin:24px 0 12px">'
                    . '<p style="font-size:12px;color:#6b7280">' . e(__('mail.reply.footer_html', $p)) . '<br><a href="' . e($url) . '">' . e($url) . '</a></p>'
                    . '</div>';
                $text = __('mail.reply.marker') . "\n\n" . Html::toText($bodyHtml)
                    . ($sig !== '' ? "\n\n-- \n" . Html::toText($sig) : '')
                    . ($listed ? "\n\n" . __('mail.reply.files_on_ticket') . "\n" . implode("\n", $listed) : '')
                    . "\n\n" . str_repeat('-', 40) . "\n" . __('mail.reply.footer_text', $p);
                return self::deliver($ticket, $c, __('mail.subject', $p), $text, $html, $attach, null, null);
            });
            if ($err !== null) {
                $errors[] = $c['email'] . ': ' . $err;
            }
        }
        return $errors;
    }

    /** Images and links of the editor are relative (/customer/media/...): mail clients need full URLs */
    private static function absoluteUrls(string $html): string
    {
        $base = rtrim((string) config('base_url'), '/');
        if ($base === '') {
            return $html;
        }
        return (string) preg_replace('#\b(src|href)="/(?!/)#i', '$1="' . $base . '/', $html);
    }

    /** Customers of the ticket (main + additional), optionally limited to some ids */
    private static function customers(array $ticket, ?array $only): array
    {
        $rows = Db::all(
            'SELECT c.* FROM customers c WHERE c.id = ?
             UNION SELECT c.* FROM ticket_customers tc JOIN customers c ON c.id = tc.customer_id WHERE tc.ticket_id = ?',
            [(int) $ticket['customer_id'], (int) $ticket['id']]
        );
        if ($only !== null) {
            $only = array_map('intval', $only);
            $rows = array_values(array_filter($rows, static fn (array $c): bool => in_array((int) $c['id'], $only, true)));
        }
        return $rows;
    }

    private static function params(array $ticket, array $c): array
    {
        return [
            'name'    => (string) $c['name'],
            'number'  => (string) $ticket['number'],
            'subject' => (string) $ticket['subject'],
            'app'     => (string) config('app_name', 'Bhelpdesk'),
        ];
    }

    /** Send one message and record it; returns an error message or null */
    private static function deliver(array $ticket, array $c, string $subject, string $text, string $html, array $attachments, ?string $auto, ?string $inReplyTo): ?string
    {
        $identity = MailIdentities::forDepartment($ticket['department_id'] !== null ? (int) $ticket['department_id'] : null);
        [$irt, $refs] = self::thread((int) $ticket['id'], $inReplyTo);
        $ok = Mailer::send((string) $c['email'], $subject, $text, $identity, [
            'html'        => $html,
            'attachments' => $attachments,
            'auto'        => $auto,
            'headers'     => [
                'In-Reply-To'        => $irt,
                'References'         => $refs,
                'X-Bhelpdesk-Ticket' => (string) $ticket['number'],
            ],
        ]);
        Db::exec(
            "INSERT INTO mail_messages (direction, identity_id, message_id, ticket_id, address, subject, status, note) VALUES ('out', ?, ?, ?, ?, ?, ?, ?)",
            [$identity['id'] ?? null, Mailer::lastMessageId(), (int) $ticket['id'], mb_substr((string) $c['email'], 0, 190), mb_substr($subject, 0, 255), $ok ? 'sent' : 'error', $ok ? null : mb_substr((string) Mailer::lastError(), 0, 255)]
        );
        return $ok ? null : (string) Mailer::lastError();
    }

    /** In-Reply-To (last message received for the ticket) and References (last 10 messages) */
    private static function thread(int $ticketId, ?string $inReplyTo): array
    {
        $ids = Db::column('SELECT message_id FROM mail_messages WHERE ticket_id = ? AND message_id IS NOT NULL ORDER BY id DESC LIMIT 10', [$ticketId]);
        $lastIn = $inReplyTo ?? Db::value("SELECT message_id FROM mail_messages WHERE ticket_id = ? AND direction = 'in' AND message_id IS NOT NULL ORDER BY id DESC LIMIT 1", [$ticketId]);
        if ($inReplyTo !== null && !in_array($inReplyTo, $ids, true)) {
            array_unshift($ids, $inReplyTo);
        }
        $refs = implode(' ', array_map(static fn ($id): string => '<' . $id . '>', array_reverse($ids)));
        return [$lastIn ? '<' . $lastIn . '>' : null, $refs !== '' ? $refs : null];
    }
}
