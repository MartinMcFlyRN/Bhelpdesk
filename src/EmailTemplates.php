<?php
declare(strict_types=1);

namespace Bhelpdesk;

/**
 * Automatic emails, editable in /op/config/templates.
 *
 * Every email is sent in a single version, whatever the language of the
 * recipient: the administrator writes in the template all the languages
 * needed (the defaults contain Italian and English). Templates are HTML
 * (same editor as signatures); the plain text version is generated.
 *
 * Placeholders: {name}. Plain values are escaped; {message}, {signature},
 * {attachments} and {excerpt} are HTML blocks; links become clickable.
 * Templates not customised use the defaults below.
 */
final class EmailTemplates
{
    /** code => [group, placeholders] */
    public const CODES = [
        'customer_opened'         => ['customer', ['customer_name', 'customer_email', 'ticket_number', 'ticket_subject', 'department_name', 'ticket_link', 'link_days', 'app_name']],
        'customer_reply'          => ['customer', ['customer_name', 'customer_email', 'ticket_number', 'ticket_subject', 'department_name', 'ticket_status', 'operator_name', 'message', 'signature', 'attachments', 'ticket_link', 'link_days', 'app_name']],
        'customer_password_setup' => ['customer', ['customer_name', 'customer_email', 'password_link', 'link_minutes', 'app_name']],
        'customer_password_reset' => ['customer', ['customer_name', 'customer_email', 'password_link', 'link_minutes', 'app_name']],
        'operator_new'            => ['operator', ['operator_name', 'ticket_number', 'ticket_subject', 'customer_name', 'customer_email', 'department_name', 'priority', 'ticket_status', 'assignee', 'source', 'excerpt', 'ticket_url', 'profile_url', 'app_name']],
        'operator_reply'          => ['operator', ['operator_name', 'ticket_number', 'ticket_subject', 'customer_name', 'customer_email', 'department_name', 'priority', 'ticket_status', 'assignee', 'excerpt', 'ticket_url', 'profile_url', 'app_name']],
        'operator_assigned'       => ['operator', ['operator_name', 'ticket_number', 'ticket_subject', 'customer_name', 'customer_email', 'department_name', 'priority', 'ticket_status', 'by', 'excerpt', 'ticket_url', 'profile_url', 'app_name']],
        'operator_password_reset' => ['operator', ['operator_name', 'username', 'password_link', 'link_minutes', 'ip', 'app_name']],
        'operator_transfer'       => ['operator', ['operator_name', 'ticket_number', 'ticket_subject', 'customer_name', 'customer_email', 'department_name', 'priority', 'ticket_status', 'assignee', 'by', 'excerpt', 'ticket_url', 'profile_url', 'app_name']],
    ];

    /** Placeholders holding HTML (not escaped) */
    private const HTML_VARS = ['message', 'signature', 'attachments', 'excerpt'];
    /** Placeholders holding a URL (made clickable) */
    private const URL_VARS = ['ticket_link', 'password_link', 'ticket_url', 'profile_url'];

    private const MARKER = '<p>##- Scrivi la tua risposta sopra questa riga / Please type your reply above this line -##</p>';

    private const DETAILS = '<p>Ticket: <strong>#{ticket_number}</strong> - {ticket_subject}<br>Cliente: {customer_name} &lt;{customer_email}&gt;<br>Reparto: {department_name} · Priorità: {priority} · Stato: {ticket_status}</p>';

    public static function defaults(string $code): array
    {
        $d = [
            'customer_opened' => [
                '[#{ticket_number}] {ticket_subject}',
                self::MARKER
                . '<p>Ciao {customer_name},</p><p>abbiamo ricevuto la tua richiesta "{ticket_subject}" e aperto il ticket <strong>#{ticket_number}</strong>. Ti risponderemo il prima possibile.</p>'
                . '<p>Puoi seguire il ticket e rispondere qui (link valido fino a {link_days} giorni dopo l\'ultima risposta):<br>{ticket_link}</p>'
                . '<p>Puoi anche rispondere a questa email per aggiungere informazioni.</p>'
                . '<p>————————————————————</p>'
                . '<p>Hello {customer_name},</p><p>we received your request "{ticket_subject}" and opened ticket <strong>#{ticket_number}</strong>. We will reply as soon as possible.</p>'
                . '<p>You can follow the ticket and reply here (link valid until {link_days} days after the last reply):<br>{ticket_link}</p>'
                . '<p>You can also reply to this email to add information.</p>'
                . '<p>{app_name}</p>',
            ],
            'customer_reply' => [
                '[#{ticket_number}] {ticket_subject}',
                self::MARKER
                . '<p>{message}</p><p>{signature}</p><p>{attachments}</p>'
                . '<p>————————————————————</p>'
                . '<p>Ticket #{ticket_number} - {ticket_subject} ({ticket_status})<br>'
                . 'Rispondi a questa email oppure apri il ticket / Reply to this email or open the ticket:<br>{ticket_link}</p>',
            ],
            'customer_password_setup' => [
                '{app_name} - crea la tua password / create your password',
                '<p>Ciao {customer_name},</p><p>per vedere i tuoi ticket di assistenza devi creare una password. Apri questo link (valido {link_minutes} minuti, utilizzabile una sola volta):<br>{password_link}</p>'
                . '<p>Se non l\'hai richiesto puoi ignorare questa email.</p>'
                . '<p>————————————————————</p>'
                . '<p>Hello {customer_name},</p><p>to see your support tickets you need to create a password. Open this link (valid for {link_minutes} minutes, single use):<br>{password_link}</p>'
                . '<p>If you did not ask for it, you can ignore this email.</p>'
                . '<p>{app_name}</p>',
            ],
            'customer_password_reset' => [
                '{app_name} - reimposta la password / reset your password',
                '<p>Ciao {customer_name},</p><p>abbiamo ricevuto una richiesta di reimpostare la tua password. Apri questo link per sceglierne una nuova (valido {link_minutes} minuti, utilizzabile una sola volta):<br>{password_link}</p>'
                . '<p>Se non l\'hai richiesto puoi ignorare questa email: la password non cambia.</p>'
                . '<p>————————————————————</p>'
                . '<p>Hello {customer_name},</p><p>we received a request to reset your password. Open this link to choose a new one (valid for {link_minutes} minutes, single use):<br>{password_link}</p>'
                . '<p>If you did not ask for it, you can ignore this email: your password does not change.</p>'
                . '<p>{app_name}</p>',
            ],
            'operator_new' => [
                '[#{ticket_number}] Nuovo ticket / New ticket: {ticket_subject}',
                '<p>Ciao {operator_name},</p><p>è stato aperto un nuovo ticket ({source}).</p>' . self::DETAILS
                . '<p>{excerpt}</p><p>Apri il ticket / Open the ticket:<br>{ticket_url}</p>'
                . '<p>————————————————————</p><p>Non rispondere a questa email: rispondi dalla pagina del ticket. Cambia i tuoi avvisi nel profilo: {profile_url}</p>',
            ],
            'operator_reply' => [
                '[#{ticket_number}] Risposta del cliente / Customer reply: {ticket_subject}',
                '<p>Ciao {operator_name},</p><p>{customer_name} ha risposto al ticket (assegnato a: {assignee}).</p>' . self::DETAILS
                . '<p>{excerpt}</p><p>Apri il ticket / Open the ticket:<br>{ticket_url}</p>'
                . '<p>————————————————————</p><p>Non rispondere a questa email: rispondi dalla pagina del ticket. Cambia i tuoi avvisi nel profilo: {profile_url}</p>',
            ],
            'operator_assigned' => [
                '[#{ticket_number}] Assegnato a te / Assigned to you: {ticket_subject}',
                '<p>Ciao {operator_name},</p><p>{by} ti ha assegnato il ticket.</p>' . self::DETAILS
                . '<p>{excerpt}</p><p>Apri il ticket / Open the ticket:<br>{ticket_url}</p>'
                . '<p>————————————————————</p><p>Non rispondere a questa email: rispondi dalla pagina del ticket. Cambia i tuoi avvisi nel profilo: {profile_url}</p>',
            ],
            'operator_password_reset' => [
                '{app_name} - reimposta la password / reset your password',
                '<p>Ciao {operator_name},</p><p>è stata richiesta la reimpostazione della password dell\'utente operatore <strong>{username}</strong> (richiesta da {ip}). Apri questo link per sceglierne una nuova (valido {link_minutes} minuti, utilizzabile una sola volta):<br>{password_link}</p>'
                . '<p>Dopo il cambio ti verrà chiesto anche il codice della verifica in due passaggi. Se non l\'hai richiesto ignora questa email: la password non cambia.</p>'
                . '<p>————————————————————</p>'
                . '<p>Hello {operator_name},</p><p>a password reset was requested for the operator user <strong>{username}</strong> (requested from {ip}). Open this link to choose a new one (valid for {link_minutes} minutes, single use):<br>{password_link}</p>'
                . '<p>After the change the two-step verification code will be asked too. If you did not ask for it, ignore this email: your password does not change.</p>'
                . '<p>{app_name}</p>',
            ],
            'operator_transfer' => [
                '[#{ticket_number}] Spostato in / Moved to {department_name}: {ticket_subject}',
                '<p>Ciao {operator_name},</p><p>{by} ha spostato il ticket nel reparto {department_name} (assegnato a: {assignee}).</p>' . self::DETAILS
                . '<p>{excerpt}</p><p>Apri il ticket / Open the ticket:<br>{ticket_url}</p>'
                . '<p>————————————————————</p><p>Non rispondere a questa email: rispondi dalla pagina del ticket. Cambia i tuoi avvisi nel profilo: {profile_url}</p>',
            ],
        ];
        [$subject, $body] = $d[$code];
        return ['subject' => $subject, 'body' => $body];
    }

    /** @return array{subject: string, body: string, custom: bool, updated_at: ?string, updated_by: ?string} */
    public static function get(string $code): array
    {
        $row = null;
        try {
            $row = Db::one('SELECT t.subject, t.body, t.updated_at, o.name AS updated_by FROM email_templates t LEFT JOIN operators o ON o.id = t.updated_by WHERE t.code = ?', [$code]);
        } catch (\PDOException) {
            // table not created yet (migration 015 not applied): defaults
        }
        if ($row) {
            return ['subject' => (string) $row['subject'], 'body' => (string) $row['body'], 'custom' => true, 'updated_at' => $row['updated_at'], 'updated_by' => $row['updated_by']];
        }
        return self::defaults($code) + ['custom' => false, 'updated_at' => null, 'updated_by' => null];
    }

    /** @return array{values: array, errors: list<string>} */
    public static function validate(array $in): array
    {
        $v = [
            'subject' => trim((string) preg_replace('/\s+/', ' ', (string) ($in['subject'] ?? ''))),
            'body'    => Html::sanitize((string) ($in['body'] ?? '')),
        ];
        $errors = [];
        if ($v['subject'] === '' || mb_strlen($v['subject']) > 255) {
            $errors[] = __('tpl.err_subject');
        }
        if (Html::isEmpty($v['body'])) {
            $errors[] = __('tpl.err_body');
        }
        return ['values' => $v, 'errors' => $errors];
    }

    public static function save(string $code, array $v, int $operatorId): void
    {
        Db::exec(
            'INSERT INTO email_templates (code, subject, body, updated_by) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE subject = VALUES(subject), body = VALUES(body), updated_by = VALUES(updated_by), updated_at = NOW()',
            [$code, $v['subject'], $v['body'], $operatorId]
        );
    }

    public static function reset(string $code): void
    {
        Db::exec('DELETE FROM email_templates WHERE code = ?', [$code]);
    }

    /**
     * Fill a template.
     * @param array<string, string> $vars placeholder => value (plain text, or HTML for HTML_VARS)
     * @return array{subject: string, html: string, text: string}
     */
    public static function render(string $code, array $vars): array
    {
        $tpl = self::get($code);
        $plain = [];
        $html = [];
        foreach ($vars as $k => $v) {
            $v = (string) $v;
            $plain['{' . $k . '}'] = in_array($k, self::HTML_VARS, true) ? Html::toText($v) : $v;
            if (in_array($k, self::HTML_VARS, true)) {
                $html['{' . $k . '}'] = $v;
            } elseif (in_array($k, self::URL_VARS, true) && $v !== '') {
                $html['{' . $k . '}'] = '<a href="' . e($v) . '">' . e($v) . '</a>';
            } else {
                $html['{' . $k . '}'] = e($v);
            }
        }
        $subject = trim((string) preg_replace('/[\r\n]+/', ' ', strtr($tpl['subject'], $plain)));

        $body = $tpl['body'];
        // a block placeholder alone in its paragraph replaces the whole paragraph
        // (no <p> around block content; empty values leave no empty paragraph)
        foreach (self::HTML_VARS as $k) {
            if (isset($html['{' . $k . '}'])) {
                $body = str_replace('<p>{' . $k . '}</p>', $html['{' . $k . '}'], $body);
            }
        }
        $body = strtr($body, $html);
        $body = (string) preg_replace('#<p>(\s|<br\s*/?>)*</p>#i', '', $body);

        $forText = (string) preg_replace('#<hr\s*/?>#i', "\n" . str_repeat('-', 40) . "\n", $body);
        $forText = (string) preg_replace('#</(p|blockquote|ul|ol)>#i', '</$1><br>', $forText); // blank line between paragraphs
        return [
            'subject' => $subject !== '' ? $subject : (string) config('app_name', 'Bhelpdesk'),
            'html'    => '<div style="font-family:' . e(Settings::MAIL_FONTS[(string) config('mail.font', 'arial')] ?? Settings::MAIL_FONTS['arial']) . ';font-size:14px;line-height:1.5;color:#1f2937">' . $body . '</div>',
            'text'    => Html::toText($forText),
        ];
    }

    /** Example values to preview / test a template */
    public static function sample(string $code, array $op): array
    {
        $base = rtrim((string) config('base_url'), '/');
        $all = [
            'customer_name'   => 'Mario Bianchi',
            'customer_email'  => 'mario.bianchi@example.com',
            'ticket_number'   => '123456',
            'ticket_subject'  => 'Stampante non funziona / Printer not working',
            'department_name' => 'Assistenza tecnica',
            'ticket_status'   => status_label('answered'),
            'priority'        => priority_label('high'),
            'operator_name'   => (string) $op['name'],
            'assignee'        => (string) $op['name'],
            'by'              => (string) $op['name'],
            'source'          => 'web',
            'message'         => '<p>Buongiorno, abbiamo verificato il problema: il tecnico passerà domani alle 9.</p><p>Good morning, we checked the issue: our technician will come tomorrow at 9.</p>',
            'signature'       => Html::isEmpty((string) Db::value('SELECT signature FROM departments ORDER BY id LIMIT 1')) ? '' : Canned::render((string) Db::value('SELECT signature FROM departments ORDER BY id LIMIT 1'), ['{operator_name}' => (string) $op['name'], '{app_name}' => (string) config('app_name', 'Bhelpdesk')]),
            'attachments'     => self::attachmentsHtml(['preventivo.pdf (240 KB)']),
            'excerpt'         => self::excerptHtml("La stampante dell'ufficio è bloccata da stamattina.\nIl display dice \"Errore 52\".", 1),
            'ticket_link'     => $base . '/customer/t/EXAMPLE-LINK',
            'password_link'   => $base . ($code === 'operator_password_reset' ? '/op/password/EXAMPLE-LINK' : '/customer/password/EXAMPLE-LINK'),
            'ticket_url'      => $base . '/op/ticket/1',
            'profile_url'     => $base . '/op/profile',
            'link_days'       => (string) AccessLinks::days(),
            'link_minutes'    => (string) ($code === 'operator_password_reset' ? OperatorPassword::LINK_MINUTES : CustomerAuth::PASSWORD_LINK_MINUTES),
            'username'        => (string) ($op['username'] ?? 'admin'),
            'ip'              => '203.0.113.10',
            'app_name'        => (string) config('app_name', 'Bhelpdesk'),
        ];
        return array_intersect_key($all, array_flip(self::CODES[$code][1]));
    }

    /** {attachments}: files that were too big to attach, listed by name */
    public static function attachmentsHtml(array $names): string
    {
        return $names ? '<p>📎 ' . implode('<br>📎 ', array_map('e', $names)) . '</p>' : '';
    }

    /** {excerpt}: customer's message quoted in operator alerts */
    public static function excerptHtml(string $text, int $files = 0): string
    {
        if (trim($text) === '' && !$files) {
            return '';
        }
        return '<blockquote>' . str_replace(["\r", "\n"], '', nl2br(e($text), false)) . ($files ? '<br>📎 ' . $files : '') . '</blockquote>';
    }
}
