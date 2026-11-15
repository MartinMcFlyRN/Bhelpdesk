<?php
declare(strict_types=1);

namespace Bhelpdesk;

/**
 * Email notifications to customers, sent with the department's sending identity.
 * Texts come from the templates of /op/config/templates (EmailTemplates):
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
            $url = AccessLinks::issue((int) $ticket['id'], (int) $c['id']);
            $mail = self::defaultLanguage(static fn (): array => EmailTemplates::render('customer_opened', self::vars($ticket, $c) + [
                'ticket_link' => $url,
                // department signature (no operator yet: {operator_name} stays empty)
                'signature'   => self::absoluteUrls(Canned::signature($ticket, [])),
            ]));
            $err = self::deliver($ticket, $c, $mail, [], 'auto-replied', $inReplyTo);
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

        $operatorName = config('hide_operator_name', false) || $op['name'] === ''
            ? self::defaultLanguage(static fn (): string => __('ticket.staff_operator'))
            : $op['name'];
        $errors = [];
        foreach (self::customers($ticket, null) as $c) {
            $url = AccessLinks::issue((int) $ticket['id'], (int) $c['id']);
            $mail = self::defaultLanguage(static fn (): array => EmailTemplates::render('customer_reply', self::vars($ticket, $c) + [
                'ticket_link'   => $url,
                'ticket_status' => status_label((string) $ticket['status']),
                'operator_name' => $operatorName,
                'message'       => $bodyHtml,
                'signature'     => self::absoluteUrls(Canned::signature($ticket, $op)),
                'attachments'   => EmailTemplates::attachmentsHtml($listed),
            ]));
            $err = self::deliver($ticket, $c, $mail, $attach, null, null);
            if ($err !== null) {
                $errors[] = $c['email'] . ': ' . $err;
            }
        }
        return $errors;
    }

    /** Labels (status, "Staff Operator") in the installation's default language */
    public static function defaultLanguage(callable $fn): mixed
    {
        return Lang::with((string) config('default_language', 'en'), $fn);
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

    private static function vars(array $ticket, array $c): array
    {
        return [
            'customer_name'   => (string) $c['name'],
            'customer_email'  => (string) $c['email'],
            'ticket_number'   => (string) $ticket['number'],
            'ticket_subject'  => (string) $ticket['subject'],
            'department_name' => (string) ($ticket['department_name'] ?? ''),
            'link_days'       => (string) AccessLinks::days(),
            'app_name'        => (string) config('app_name', 'Bhelpdesk'),
        ];
    }

    /** Send one message and record it; returns an error message or null */
    private static function deliver(array $ticket, array $c, array $mail, array $attachments, ?string $auto, ?string $inReplyTo): ?string
    {
        $subject = $mail['subject'];
        $identity = MailIdentities::forDepartment($ticket['department_id'] !== null ? (int) $ticket['department_id'] : null);
        [$irt, $refs] = self::thread((int) $ticket['id'], $inReplyTo);
        $ok = Mailer::send((string) $c['email'], $subject, $mail['text'], $identity, [
            'html'        => $mail['html'],
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
        $ids = Db::column("SELECT message_id FROM mail_messages WHERE ticket_id = ? AND message_id IS NOT NULL AND status NOT IN ('alert', 'error') ORDER BY id DESC LIMIT 10", [$ticketId]);
        $lastIn = $inReplyTo ?? Db::value("SELECT message_id FROM mail_messages WHERE ticket_id = ? AND direction = 'in' AND message_id IS NOT NULL ORDER BY id DESC LIMIT 1", [$ticketId]);
        if ($inReplyTo !== null && !in_array($inReplyTo, $ids, true)) {
            array_unshift($ids, $inReplyTo);
        }
        $refs = implode(' ', array_map(static fn ($id): string => '<' . $id . '>', array_reverse($ids)));
        return [$lastIn ? '<' . $lastIn . '>' : null, $refs !== '' ? $refs : null];
    }
}
