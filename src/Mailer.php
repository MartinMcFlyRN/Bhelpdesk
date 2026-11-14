<?php
declare(strict_types=1);

namespace Bhelpdesk;

/**
 * Outgoing mail (plain text, UTF-8).
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

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    public static function send(string $to, string $subject, string $body, ?array $identity = null): bool
    {
        self::$lastError = null;
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
        $encodedBody = self::encodeBody($body);

        try {
            if ($driver === 'log') {
                return self::log($headers, $body, $identity);
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

    /** Message headers (Subject/From name as RFC 2047 encoded words). */
    public static function headers(string $from, string $fromName, string $to, string $subject): array
    {
        $domain = substr(strrchr($from, '@') ?: '@localhost', 1);
        return [
            'Date'                      => date('r'),
            'From'                      => self::encode($fromName) . ' <' . $from . '>',
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

    private static function log(array $headers, string $body, ?array $identity): bool
    {
        $file = rtrim((string) (config('storage_path') ?: BHD_ROOT . '/storage'), '/') . '/mail.log';
        $entry = str_repeat('=', 72) . "\n"
            . 'Date: ' . $headers['Date'] . "\n"
            . 'To: ' . $headers['To'] . "\n"
            . 'From: ' . $headers['From'] . ($identity ? ' (identity #' . $identity['id'] . ')' : '') . "\n"
            . 'Subject: ' . self::decode($headers['Subject']) . "\n\n"
            . $body . "\n";
        if (@file_put_contents($file, $entry, FILE_APPEND | LOCK_EX) === false) {
            throw new \RuntimeException('cannot write ' . $file);
        }
        return true;
    }

    private static function encode(string $s): string
    {
        return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }

    private static function decode(string $s): string
    {
        return preg_match('/^=\?UTF-8\?B\?(.*)\?=$/', $s, $m) ? (string) base64_decode($m[1]) : $s;
    }
}
