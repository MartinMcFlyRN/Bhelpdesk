<?php
declare(strict_types=1);

namespace Bhelpdesk;

use RuntimeException;

/**
 * Downloads the POP3 mailboxes of the email identities and turns messages into tickets.
 *
 *  - new message            -> new ticket in the identity's department (Cc people added as customers)
 *  - reply to a ticket      -> customer reply; matched by In-Reply-To/References (Message-IDs we
 *                              sent or received) or by "[#123456]" in the subject when the sender
 *                              is already a customer of that ticket
 *  - automatic messages     -> ignored: auto-replies, bounces, delivery reports, mailing lists,
 *                              our own addresses, duplicates, too many new tickets from one sender
 *  - retention on the server: delete after download, keep the last N days, or keep forever
 *
 * Every message is recorded in mail_messages (by POP3 UIDL), so it is processed once.
 * Run by bin/fetch-mail.php (cron) or the "Download now" button.
 */
final class MailFetcher
{
    public const MAX_PER_RUN = 50;          // messages downloaded per identity per run
    private const SMALL_INLINE = 15360;     // inline images below 15 KB (logos in signatures) are skipped

    /** Exclusive run lock (two crons, cron + button): null when another run is in progress */
    public static function lock()
    {
        $file = rtrim((string) (config('storage_path') ?: BHD_ROOT . '/storage'), '/') . '/fetch-mail.lock';
        $fh = @fopen($file, 'c');
        if (!$fh || !flock($fh, LOCK_EX | LOCK_NB)) {
            return null;
        }
        return $fh;
    }

    /**
     * Download every active identity with POP3 enabled (or just one).
     * @return list<array{identity: string, ok: bool, message: string, stats: array}>
     */
    public static function run(?int $identityId = null): array
    {
        $sql = 'SELECT * FROM mail_identities WHERE active = 1 AND pop_enabled = 1 AND pop_host <> \'\'';
        $rows = $identityId ? Db::all($sql . ' AND id = ?', [$identityId]) : Db::all($sql . ' ORDER BY id');
        $out = [];
        foreach ($rows as $i) {
            try {
                $stats = self::fetchIdentity($i);
                $msg = self::summary($stats);
                MailIdentities::markPop((int) $i['id'], true, $msg);
                $out[] = ['identity' => $i['email'], 'ok' => true, 'message' => $msg, 'stats' => $stats];
            } catch (\Throwable $ex) {
                MailIdentities::markPop((int) $i['id'], false, $ex->getMessage());
                $out[] = ['identity' => $i['email'], 'ok' => false, 'message' => $ex->getMessage(), 'stats' => []];
            }
        }
        return $out;
    }

    public static function summary(array $s): string
    {
        return __('mailin.summary', [
            'new' => $s['downloaded'] ?? 0, 'tickets' => $s['ticket'] ?? 0, 'replies' => $s['reply'] ?? 0,
            'ignored' => $s['ignored'] ?? 0, 'errors' => $s['error'] ?? 0, 'deleted' => $s['deleted'] ?? 0,
        ]);
    }

    /** @return array<string, int> counters */
    public static function fetchIdentity(array $i): array
    {
        $stats = ['downloaded' => 0, 'ticket' => 0, 'reply' => 0, 'ignored' => 0, 'error' => 0, 'deleted' => 0, 'left' => 0];
        $maxBytes = max(1, (int) config('mail.max_message_mb', 30)) * 1048576;
        $pop = MailIdentities::pop3($i);
        try {
            $pop->connect();
            $uidl = $pop->uidl();
            $sizes = $pop->list();
            $known = [];
            foreach (Db::all("SELECT pop_uid, status, created_at FROM mail_messages WHERE direction = 'in' AND identity_id = ? AND pop_uid IS NOT NULL", [(int) $i['id']]) as $r) {
                $known[$r['pop_uid']] = $r;
            }
            foreach ($uidl as $n => $uid) {
                if (isset($known[$uid])) {
                    if (self::shouldDelete($i, $known[$uid])) {
                        $pop->dele($n);
                        $stats['deleted']++;
                    }
                    continue;
                }
                if ($stats['downloaded'] >= self::MAX_PER_RUN) {
                    $stats['left']++;
                    continue;
                }
                $stats['downloaded']++;
                if (($sizes[$n] ?? 0) > $maxBytes) {
                    self::record($i, $uid, null, null, null, null, 'big', __('mailin.too_big', ['size' => Attachments::humanSize($sizes[$n])]));
                    $stats['ignored']++;
                    continue; // left on the server: too big to process
                }
                try {
                    $r = self::process($i, $pop->retr($n), $uid);
                } catch (\Throwable $ex) {
                    error_log('Bhelpdesk: email ' . $uid . ' of ' . $i['email'] . ' not processed: ' . $ex->getMessage());
                    self::record($i, $uid, null, null, null, null, 'error', mb_substr($ex->getMessage(), 0, 250));
                    $stats['error']++;
                    continue; // left on the server
                }
                $stats[$r['status']] = ($stats[$r['status']] ?? 0) + 1;
                if ($i['pop_keep'] === 'delete') {
                    $pop->dele($n);
                    $stats['deleted']++;
                }
            }
        } finally {
            $pop->quit(); // QUIT commits the deletions
        }
        return $stats;
    }

    /** Retention: may this already processed message be removed from the server? */
    private static function shouldDelete(array $i, array $rec): bool
    {
        if ($i['pop_keep'] === 'forever') {
            return false;
        }
        if ($i['pop_keep'] === 'delete') {
            // processed messages are deleted right away; errors and too big ones stay
            return in_array($rec['status'], ['ticket', 'reply', 'ignored'], true);
        }
        return strtotime((string) $rec['created_at']) < time() - max(1, (int) $i['pop_keep_days']) * 86400;
    }

    /**
     * Turn one raw message into a ticket or a reply (or ignore it).
     * @return array{status: string, ticket_id: ?int, note: string}
     */
    public static function process(array $identity, string $raw, ?string $uid = null): array
    {
        $m = Mime::parse($raw);
        $subject = trim((string) preg_replace('/\s+/u', ' ', $m->header('subject')));
        $subject = mb_substr($subject !== '' ? $subject : __('mailin.no_subject'), 0, 255);
        $msgId = $m->ids('message-id')[0] ?? null;
        $sender = self::sender($m);
        $address = $sender['email'] ?? null;

        $ignore = static function (string $note) use ($identity, $uid, $msgId, $address, $subject): array {
            self::record($identity, $uid, $msgId, $address, $subject, null, 'ignored', $note);
            return ['status' => 'ignored', 'ticket_id' => null, 'note' => $note];
        };

        if (!$sender) {
            return $ignore(__('mailin.ign_no_sender'));
        }
        if ($reason = self::automated($m, $sender['email'])) {
            return $ignore($reason);
        }
        if ($msgId && Db::value("SELECT 1 FROM mail_messages WHERE direction = 'in' AND message_id = ? AND status IN ('ticket','reply')", [$msgId])) {
            return $ignore(__('mailin.ign_duplicate'));
        }

        // ---- which ticket? ----
        $ticket = null;
        $strong = false;
        $refs = array_values(array_unique(array_merge($m->ids('in-reply-to'), array_reverse($m->ids('references')))));
        if ($refs) {
            $in = implode(',', array_fill(0, count($refs), '?'));
            $tid = Db::value("SELECT ticket_id FROM mail_messages WHERE message_id IN ($in) AND ticket_id IS NOT NULL ORDER BY id DESC LIMIT 1", $refs);
            if ($tid) {
                $ticket = self::followMerged((int) $tid);
                $strong = $ticket !== null;
            }
        }
        $customer = Customers::findByEmail($sender['email']);
        if (!$ticket && preg_match('/\[#(\d{6})\]/', $subject, $mm)) {
            $t = Tickets::findByNumber($mm[1]);
            $t = $t ? self::followMerged((int) $t['id']) : null;
            // the number alone is guessable: only a customer of the ticket may reply this way
            if ($t && $customer && Tickets::customerHasAccess($t, (int) $customer['id'])) {
                $ticket = $t;
            }
        }
        if ($ticket && $ticket['status'] === 'closed' && !config('allow_reopen', true)) {
            $ticket = null; // replies to closed tickets open a new one
        }

        // ---- body and attachments ----
        $text = self::bodyText($m, $ticket !== null);
        $items = [];
        preg_match_all('/cid:([^"\'\s>)]+)/i', (string) $m->html(), $cm);
        $usedCids = array_map('strtolower', $cm[1]);
        foreach ($m->attachments() as $a) {
            // images embedded in the HTML (logos of signatures) are skipped when small
            $embedded = $a['inline'] || ($a['cid'] !== '' && in_array(strtolower($a['cid']), $usedCids, true));
            if ($embedded && str_starts_with($a['mime'], 'image/') && strlen($a['data']) < self::SMALL_INLINE) {
                continue;
            }
            $items[] = ['name' => $a['name'], 'data' => $a['data']];
        }
        $att = $items ? Attachments::fromData($items, 'operator') : ['files' => [], 'rejected' => []];
        if ($text === '' && !$att['files']) {
            Attachments::discard($att['files']);
            return $ignore(__('mailin.ign_empty'));
        }
        if ($text === '') {
            $text = __('mailin.only_attachments');
        }

        try {
            if ($ticket) {
                if (!$customer) {
                    $customer = Customers::find(self::customerFor($sender));
                }
                if (!Tickets::customerHasAccess($ticket, (int) $customer['id'])) {
                    Tickets::addParticipantSystem($ticket, (int) $customer['id'], 'E-mail');
                }
                $messageId = Tickets::customerReply($ticket, $text, '', (int) $customer['id'], $att['files'], true);
                $ticketId = (int) $ticket['id'];
                $status = 'reply';
            } else {
                $limit = (int) config('mail.max_new_per_hour', 10);
                $recent = (int) Db::value("SELECT COUNT(*) FROM mail_messages WHERE direction = 'in' AND status = 'ticket' AND address = ? AND created_at > NOW() - INTERVAL 1 HOUR", [$sender['email']]);
                if ($limit > 0 && $recent >= $limit) {
                    Attachments::discard($att['files']);
                    return $ignore(__('mailin.ign_rate', ['n' => $limit]));
                }
                $lang = (string) ($customer['language'] ?? config('default_language', 'en'));
                $name = $customer ? (string) $customer['name'] : self::displayName($sender);
                $t = Tickets::create($name, $sender['email'], (int) $identity['department_id'], $subject, $text, '', $lang, $att['files']);
                Db::exec('UPDATE ticket_messages SET via_email = 1 WHERE id = ?', [$t['message_id']]);
                $ticketId = $t['id'];
                $messageId = $t['message_id'];
                $status = 'ticket';
                if (config('mail.cc_participants', true)) {
                    self::addCopies($m, Tickets::find($ticketId), $sender['email']);
                }
            }
        } catch (\Throwable $ex) {
            Attachments::discard($att['files']);
            throw $ex;
        }

        if ($att['rejected']) {
            Tickets::addMessage($ticketId, 'system', null, json_encode(['event' => 'email_files_skipped', 'files' => $att['rejected']], JSON_UNESCAPED_UNICODE), true, null);
        }
        self::record($identity, $uid, $msgId, $sender['email'], $subject, $ticketId, $status, $att['rejected'] ? __('mailin.files_skipped', ['n' => count($att['rejected'])]) : null, $messageId);

        if ($status === 'ticket') {
            Notifier::ticketOpened($ticketId, null, $msgId);
        }
        return ['status' => $status, 'ticket_id' => $ticketId, 'note' => ''];
    }

    // ---------- helpers ----------

    /**
     * Sender of the message. Messages from no-reply addresses (website forms,
     * notification services) use Reply-To when present.
     * @return array{email: string, name: string}|null
     */
    private static function sender(Mime $m): ?array
    {
        $from = $m->addresses('from')[0] ?? null;
        $replyTo = $m->addresses('reply-to')[0] ?? null;
        if ($from && $replyTo && self::isNoReply($from['email'])) {
            return $replyTo + ['name' => ''];
        }
        return $from;
    }

    private static function isNoReply(string $email): bool
    {
        $local = strtolower(strstr($email, '@', true) ?: $email);
        return (bool) preg_match('/^(no-?reply|do-?not-?reply|noreply-.*|.*-noreply|wordpress|www-data)$/', $local);
    }

    /** Reason why a message is automatic (and must not open tickets nor get answers), or '' */
    private static function automated(Mime $m, string $sender): string
    {
        $own = array_map('strtolower', Db::column('SELECT email FROM mail_identities'));
        if (in_array($sender, $own, true)) {
            return __('mailin.ign_own');
        }
        if ($m->has('x-bhelpdesk-ticket')) {
            return __('mailin.ign_own');
        }
        $auto = strtolower($m->header('auto-submitted'));
        if ($auto !== '' && $auto !== 'no') {
            return __('mailin.ign_auto', ['h' => 'Auto-Submitted: ' . $auto]);
        }
        $prec = strtolower($m->header('precedence'));
        if (in_array($prec, ['bulk', 'junk', 'list', 'auto_reply'], true)) {
            return __('mailin.ign_auto', ['h' => 'Precedence: ' . $prec]);
        }
        foreach (['x-autoreply', 'x-autorespond', 'x-auto-reply-from', 'x-mail-autoreply'] as $h) {
            if ($m->has($h)) {
                return __('mailin.ign_auto', ['h' => $h]);
            }
        }
        if ($m->has('list-id')) {
            return __('mailin.ign_list');
        }
        $local = strtolower(strstr($sender, '@', true) ?: '');
        if ($m->isReport() || in_array($local, ['mailer-daemon', 'postmaster'], true) || trim($m->rawHeader('return-path')) === '<>') {
            return __('mailin.ign_bounce');
        }
        if (self::isNoReply($sender)) {
            return __('mailin.ign_noreply');
        }
        return '';
    }

    private static function followMerged(int $ticketId): ?array
    {
        for ($i = 0; $i < 10; $i++) {
            $t = Tickets::find($ticketId);
            if (!$t || $t['merged_into'] === null) {
                return $t;
            }
            $ticketId = (int) $t['merged_into'];
        }
        return null;
    }

    /** Plain text body; for replies the quoted previous messages are removed */
    public static function bodyText(Mime $m, bool $isReply): string
    {
        $text = $m->text();
        if ($text === null || trim($text) === '') {
            $html = (string) $m->html();
            if ($isReply) {
                // cut the HTML where the quoted conversation starts
                foreach (['/<div[^>]+class="?gmail_quote/i', '/<blockquote/i', '/<div[^>]+id="?(appendonsend|divRplyFwdMsg)/i', '/<div[^>]+class="?moz-cite-prefix/i', '/##-/'] as $re) {
                    if (preg_match($re, $html, $mm, PREG_OFFSET_CAPTURE)) {
                        $html = substr($html, 0, $mm[0][1]);
                    }
                }
            }
            $html = (string) preg_replace('#<(head|style|script|title)\b.*?</\1\s*>#is', '', $html);
            $text = Html::toText($html);
        }
        $text = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $text);
        $text = self::cutAtMarker($text);
        if ($isReply) {
            $text = self::stripQuote($text);
        }
        $text = trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
        return mb_substr($text, 0, 100000);
    }

    /** Everything from our "reply above this line" marker on is the quoted notification */
    private static function cutAtMarker(string $text): string
    {
        $p = strpos($text, '##-');
        if ($p === false) {
            return $text;
        }
        $lineStart = strrpos(substr($text, 0, $p), "\n");
        return substr($text, 0, $lineStart === false ? 0 : $lineStart);
    }

    /** Remove the quoted message under a reply ("On ... wrote:", Outlook headers, "> " lines) */
    public static function stripQuote(string $text): string
    {
        $lines = explode("\n", $text);
        $n = count($lines);
        for ($i = 0; $i < $n; $i++) {
            $l = trim($lines[$i]);
            $two = $l . ' ' . trim($lines[$i + 1] ?? '');
            if (preg_match('/^-{2,}\s*(original message|messaggio originale|ursprüngliche nachricht|message d\'origine|mensaje original)\s*-{2,}$/i', $l)
                || preg_match('/^_{10,}$/', $l)) {
                return implode("\n", array_slice($lines, 0, $i));
            }
            // "On Mon, 1 Jan 2026 at 10:00, Name <a@b> wrote:" (possibly wrapped on two lines)
            $wrote = '/^(on|il|am|le|el|op|em)\b.{4,250}\b(wrote|ha scritto|schrieb|a écrit|escribió|schreef|escreveu)\s*:$/iu';
            if (preg_match($wrote, $l)) {
                return implode("\n", array_slice($lines, 0, $i));
            }
            if (preg_match($wrote, $two) && !preg_match($wrote, trim($lines[$i + 1] ?? ''))) {
                return implode("\n", array_slice($lines, 0, $i));
            }
            // Outlook: "From: ..." followed by "Sent:/Date:" within the next lines
            if (preg_match('/^\*?(from|da|von|de)\s*:\*?\s+\S/i', $l)) {
                $next = implode("\n", array_slice($lines, $i + 1, 4));
                if (preg_match('/^\*?(sent|date|inviato|data|gesendet|envoyé|enviado)\s*:/im', $next)) {
                    return implode("\n", array_slice($lines, 0, $i));
                }
            }
        }
        // trailing "> quoted" block
        while ($lines && (trim(end($lines)) === '' || str_starts_with(ltrim(end($lines)), '>'))) {
            array_pop($lines);
        }
        return implode("\n", $lines);
    }

    private static function displayName(array $sender): string
    {
        $name = trim($sender['name']);
        if ($name === '' || filter_var($name, FILTER_VALIDATE_EMAIL)) {
            $name = ucwords(str_replace(['.', '_', '-'], ' ', (string) strstr($sender['email'], '@', true)));
        }
        return mb_substr($name !== '' ? $name : $sender['email'], 0, 100);
    }

    private static function customerFor(array $sender): int
    {
        $res = Customers::resolve($sender['email'], self::displayName($sender));
        if (!$res['id']) {
            throw new RuntimeException('Cannot create customer ' . $sender['email'] . ': ' . $res['error']);
        }
        return (int) $res['id'];
    }

    /** People in To/Cc of a new message become additional customers (not our addresses, not operators) */
    private static function addCopies(Mime $m, array $ticket, string $sender): void
    {
        $skip = array_merge([$sender], array_map('strtolower', Db::column('SELECT email FROM mail_identities')), array_map('strtolower', Db::column('SELECT email FROM operators')));
        $done = [];
        foreach (array_merge($m->addresses('to'), $m->addresses('cc')) as $a) {
            if (in_array($a['email'], $skip, true) || isset($done[$a['email']]) || count($done) >= 20) {
                continue;
            }
            $done[$a['email']] = true;
            Tickets::addParticipantSystem($ticket, self::customerFor($a), 'E-mail');
        }
    }

    private static function record(array $identity, ?string $uid, ?string $msgId, ?string $address, ?string $subject, ?int $ticketId, string $status, ?string $note, ?int $ticketMessageId = null): void
    {
        Db::exec(
            "INSERT INTO mail_messages (direction, identity_id, pop_uid, message_id, ticket_id, ticket_message_id, address, subject, status, note)
             VALUES ('in', ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status = VALUES(status), note = VALUES(note), ticket_id = VALUES(ticket_id), ticket_message_id = VALUES(ticket_message_id)",
            [(int) $identity['id'], $uid, $msgId !== null ? mb_substr($msgId, 0, 255) : null, $ticketId, $ticketMessageId, $address !== null ? mb_substr($address, 0, 190) : null, $subject !== null ? mb_substr($subject, 0, 255) : null, $status, $note !== null ? mb_substr($note, 0, 255) : null]
        );
    }
}
