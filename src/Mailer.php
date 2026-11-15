<?php
declare(strict_types=1);

namespace Bhelpdesk;

/**
 * Outgoing mail (UTF-8 text, optional HTML version and attachments).
 *
 * The sender is an email identity (/op/config/email): the identity passed by
 * the caller (e.g. the department's sending identity) or the default identity
 * for system emails. Without identities the "Default from" of General settings
 * is used.
 *
 * Transport (config.php 'mail.driver'):
 *  - 'auto' (default): SMTP of the identity when configured, otherwise PHP mail();
 *  - 'smtp': SMTP only (fails when the identity has no SMTP server);
 *  - 'mail': PHP mail() through the server's sendmail/postfix;
 *  - 'log' : nothing is sent, messages are appended to storage/mail.log (testing).
 */
final class Mailer
{
    private static ?string $lastError = null;
    private static ?string $lastMessageId = null;

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    /** Message-ID (without < >) of the last message sent */
    public static function lastMessageId(): ?string
    {
        return self::$lastMessageId;
    }

    /**
     * Send a message. $body is the plain text version.
     * $opt:
     *  - html:        HTML version (multipart/alternative with the text)
     *  - attachments: list of [name, mime, path] (multipart/mixed)
     *  - headers:     extra headers, e.g. In-Reply-To, References, Reply-To
     *  - auto:        Auto-Submitted value ('auto-generated' default, 'auto-replied', or null for human replies)
     */
    public static function send(string $to, string $subject, string $body, ?array $identity = null, array $opt = []): bool
    {
        self::$lastError = null;
        self::$lastMessageId = null;
        $to = trim($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $to . $subject)) {
            self::$lastError = 'Invalid recipient or subject';
            return false;
        }
        $identity ??= MailIdentities::defaultIdentity();
        $from = $identity ? (string) $identity['email'] : (string) config('mail.from', 'helpdesk@localhost');
        $fromName = $identity ? (string) $identity['name'] : (string) config('mail.from_name', (string) config('app_name', 'Bhelpdesk'));
        $driver = (string) config('mail.driver', 'auto');

        $headers = self::headers($from, $fromName, $to, $subject);
        if (array_key_exists('auto', $opt)) {
            if ($opt['auto'] === null) {
                unset($headers['Auto-Submitted']);
            } else {
                $headers['Auto-Submitted'] = (string) $opt['auto'];
            }
        }
        foreach ((array) ($opt['headers'] ?? []) as $k => $v) {
            if ($v !== null && $v !== '' && !preg_match('/[\r\n]/', (string) $v)) {
                $headers[(string) $k] = (string) $v;
            }
        }
        [$contentHeaders, $encodedBody] = self::content($body, (string) ($opt['html'] ?? ''), (array) ($opt['attachments'] ?? []));
        unset($headers['Content-Type'], $headers['Content-Transfer-Encoding']);
        $headers += $contentHeaders;
        self::$lastMessageId = trim($headers['Message-ID'], '<>');

        try {
            if ($driver === 'log') {
                return self::log($headers, $body, $identity, (array) ($opt['attachments'] ?? []));
            }
            $useSmtp = $identity && $identity['smtp_host'] !== '' && in_array($driver, ['auto', 'smtp'], true);
            if ($driver === 'smtp' && !$useSmtp) {
                throw new \RuntimeException('No SMTP server configured for ' . $from);
            }
            if ($useSmtp) {
                MailIdentities::smtp($identity)->send($from, [$to], self::raw($headers, $encodedBody));
                return true;
            }
            // PHP mail(): To and Subject are separate arguments
            $extra = array_diff_key($headers, ['To' => 1, 'Subject' => 1]);
            $params = filter_var($from, FILTER_VALIDATE_EMAIL) ? '-f' . $from : '';
            if (!mail($to, $headers['Subject'], $encodedBody, $extra, $params)) {
                throw new \RuntimeException('mail() failed');
            }
            return true;
        } catch (\Throwable $ex) {
            self::$lastError = $ex->getMessage();
            error_log('Bhelpdesk: email to ' . $to . ' failed: ' . $ex->getMessage());
            return false;
        }
    }

    /**
     * MIME body: text only, text + HTML (alternative), plus attachments (mixed).
     * @return array{0: array<string, string>, 1: string} content headers, encoded body
     */
    private static function content(string $text, string $html, array $attachments): array
    {
        $textPart = ['text/plain; charset=UTF-8', self::encodeBody($text)];
        if ($html !== '') {
            [$html, $inline] = self::inlineImages($html);
            $htmlPart = ['Content-Type' => 'text/html; charset=UTF-8', 'Content-Transfer-Encoding' => 'base64', '' => self::encodeBody($html)];
            if ($inline) {
                // images of the editor (logo of the signature...) travel inside the message
                $rb = 'rel-' . bin2hex(random_bytes(8));
                $htmlPart = ['Content-Type' => 'multipart/related; type="text/html"; boundary="' . $rb . '"', '' => self::multipart($rb, array_merge([$htmlPart], $inline))];
            }
            $b = 'alt-' . bin2hex(random_bytes(8));
            $main = ['multipart/alternative; boundary="' . $b . '"', self::multipart($b, [
                ['Content-Type' => 'text/plain; charset=UTF-8', 'Content-Transfer-Encoding' => 'base64', '' => $textPart[1]],
                $htmlPart,
            ]), null];
        } else {
            $main = [$textPart[0], $textPart[1], 'base64'];
        }
        $files = [];
        foreach ($attachments as [$name, $mime, $path]) {
            $data = @file_get_contents($path);
            if ($data === false) {
                continue;
            }
            $files[] = [
                'Content-Type'              => $mime . '; name="' . self::encodeParam($name) . '"',
                'Content-Disposition'       => 'attachment; filename="' . self::encodeParam($name) . '"',
                'Content-Transfer-Encoding' => 'base64',
                ''                          => rtrim(chunk_split(base64_encode($data), 76, "\r\n")),
            ];
        }
        if (!$files) {
            return [array_filter(['Content-Type' => $main[0], 'Content-Transfer-Encoding' => $main[2]]), $main[1]];
        }
        $b = 'mix-' . bin2hex(random_bytes(8));
        $first = ['Content-Type' => $main[0]] + ($main[2] ? ['Content-Transfer-Encoding' => $main[2]] : []) + ['' => $main[1]];
        return [['Content-Type' => 'multipart/mixed; boundary="' . $b . '"'], self::multipart($b, array_merge([$first], $files))];
    }

    /**
     * Images uploaded with the editor (/customer/media/...) become inline parts
     * referenced by cid:, so they show even when the mail client blocks remote
     * images. Up to 3 MB in total; the others keep their URL.
     * @return array{0: string, 1: list<array<string, string>>} html, MIME parts
     */
    private static function inlineImages(string $html): array
    {
        if (!preg_match_all('#\ssrc="((?:https?://[^"/]+)?/customer/media/([a-f0-9]{32}\.(png|jpe?g|gif|webp)))"#i', $html, $m, PREG_SET_ORDER)) {
            return [$html, []];
        }
        $types = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];
        $parts = [];
        $done = [];
        $total = 0;
        foreach ($m as [, $src, $file, $ext]) {
            $file = strtolower($file);
            $path = Media::dir() . '/' . $file;
            if (!isset($done[$file])) {
                $size = is_file($path) ? (int) filesize($path) : 0;
                if ($size === 0 || $total + $size > 3 * 1024 * 1024 || ($data = @file_get_contents($path)) === false) {
                    continue;
                }
                $total += $size;
                $done[$file] = $file . '@bhelpdesk';
                $parts[] = [
                    'Content-Type'              => $types[strtolower($ext)] . '; name="' . $file . '"',
                    'Content-Transfer-Encoding' => 'base64',
                    'Content-ID'                => '<' . $done[$file] . '>',
                    'Content-Disposition'       => 'inline; filename="' . $file . '"',
                    ''                          => rtrim(chunk_split(base64_encode($data), 76, "\r\n")),
                ];
            }
            $html = str_replace('src="' . $src . '"', 'src="cid:' . $done[$file] . '"', $html);
        }
        return [$html, $parts];
    }

    /** @param list<array<string, string>> $parts headers + '' => body */
    private static function multipart(string $boundary, array $parts): string
    {
        $out = "This is a multi-part message in MIME format.\r\n";
        foreach ($parts as $p) {
            $out .= "\r\n--" . $boundary . "\r\n";
            foreach ($p as $k => $v) {
                if ($k !== '') {
                    $out .= $k . ': ' . $v . "\r\n";
                }
            }
            $out .= "\r\n" . $p[''];
        }
        return $out . "\r\n--" . $boundary . "--\r\n";
    }

    /** File name inside a quoted parameter (RFC 2047 word when not plain ASCII) */
    private static function encodeParam(string $s): string
    {
        $s = str_replace(['"', '\\', "\r", "\n"], '', $s);
        return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }

    /** Message headers (Subject/From name as RFC 2047 encoded words). */
    public static function headers(string $from, string $fromName, string $to, string $subject): array
    {
        $domain = substr(strrchr($from, '@') ?: '@localhost', 1);
        return [
            'Date'                      => date('r'),
            'From'                      => self::encode($fromName, true) . ' <' . $from . '>',
            'To'                        => $to,
            'Subject'                   => self::encode($subject),
            'Message-ID'                => '<' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
            'MIME-Version'              => '1.0',
            'Content-Type'              => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => 'base64',
            'X-Mailer'                  => 'Bhelpdesk',
            'Auto-Submitted'            => 'auto-generated',
        ];
    }

    public static function encodeBody(string $body): string
    {
        return rtrim(chunk_split(base64_encode(str_replace(["\r\n", "\r"], "\n", $body)), 76, "\r\n"));
    }

    /** Complete RFC 5322 message, ready for SMTP DATA. */
    public static function raw(array $headers, string $encodedBody): string
    {
        $raw = '';
        foreach ($headers as $k => $v) {
            $raw .= $k . ': ' . $v . "\r\n";
        }
        return $raw . "\r\n" . $encodedBody;
    }

    private static function log(array $headers, string $body, ?array $identity, array $attachments = []): bool
    {
        $file = rtrim((string) (config('storage_path') ?: BHD_ROOT . '/storage'), '/') . '/mail.log';
        $entry = str_repeat('=', 72) . "\n"
            . 'Date: ' . $headers['Date'] . "\n"
            . 'To: ' . $headers['To'] . "\n"
            . 'From: ' . $headers['From'] . ($identity ? ' (identity #' . $identity['id'] . ')' : '') . "\n"
            . 'Subject: ' . self::decode($headers['Subject']) . "\n"
            . (isset($headers['In-Reply-To']) ? 'In-Reply-To: ' . $headers['In-Reply-To'] . "\n" : '')
            . ($attachments ? 'Attachments: ' . implode(', ', array_column($attachments, 0)) . "\n" : '')
            . "\n" . $body . "\n";
        if (@file_put_contents($file, $entry, FILE_APPEND | LOCK_EX) === false) {
            throw new \RuntimeException('cannot write ' . $file);
        }
        return true;
    }

    private static function encode(string $s, bool $phrase = false): string
    {
        if (preg_match('/[^\x20-\x7E]/', $s)) {
            return '=?UTF-8?B?' . base64_encode($s) . '?=';
        }
        // ASCII with special characters (e.g. "Rossi, Mario"): quoted string
        return $phrase && preg_match('/[()<>@,;:\\\\".\\[\\]]/', $s) ? '"' . addcslashes($s, '"\\\\') . '"' : $s;
    }

    private static function decode(string $s): string
    {
        return preg_match('/^=\?UTF-8\?B\?(.*)\?=$/', $s, $m) ? (string) base64_decode($m[1]) : $s;
    }
}
