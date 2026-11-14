<?php
declare(strict_types=1);

namespace Bhelpdesk;

/**
 * Minimal mailer (plain text, UTF-8).
 *
 * Drivers (config 'mail.driver'):
 *  - 'log'  (default): messages are appended to storage/mail.log instead of being sent.
 *           Useful for testing; read the links from the log.
 *  - 'mail': PHP mail() through the server's sendmail/postfix.
 * SMTP and the ticket notifications will come with the email phase.
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $body): bool
    {
        $to = trim($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $to . $subject)) {
            return false;
        }
        $from = (string) config('mail.from', 'helpdesk@localhost');
        $fromName = (string) config('mail.from_name', (string) config('app_name', 'Bhelpdesk'));
        $driver = (string) config('mail.driver', 'log');

        $headers = [
            'From'                      => self::encode($fromName) . ' <' . $from . '>',
            'MIME-Version'              => '1.0',
            'Content-Type'              => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => '8bit',
            'X-Mailer'                  => 'Bhelpdesk',
        ];

        if ($driver === 'mail') {
            // envelope sender only when it is a plain valid address (it ends up on the sendmail command line)
            $params = filter_var($from, FILTER_VALIDATE_EMAIL) ? '-f' . $from : '';
            $ok = mail($to, self::encode($subject), $body, $headers, $params);
            if (!$ok) {
                error_log('Bhelpdesk: mail() failed for ' . $to);
            }
            return $ok;
        }

        // 'log' driver
        $file = rtrim((string) (config('storage_path') ?: BHD_ROOT . '/storage'), '/') . '/mail.log';
        $entry = str_repeat('=', 72) . "\n"
            . 'Date: ' . date('r') . "\n"
            . 'To: ' . $to . "\n"
            . 'From: ' . $headers['From'] . "\n"
            . 'Subject: ' . $subject . "\n\n"
            . $body . "\n";
        $ok = @file_put_contents($file, $entry, FILE_APPEND | LOCK_EX) !== false;
        if (!$ok) {
            error_log('Bhelpdesk: cannot write ' . $file);
        }
        return $ok;
    }

    private static function encode(string $s): string
    {
        return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }
}
