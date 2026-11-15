<?php
declare(strict_types=1);

namespace Bhelpdesk;

/**
 * "Forgot your password?" for operators.
 *
 * The link is single-use, valid LINK_MINUTES, stored as sha256 only; only the
 * newest link of an operator works. Setting the new password does NOT sign in:
 * the operator signs in with the new password and the second factor, and the
 * trusted computers are forgotten.
 */
final class OperatorPassword
{
    public const LINK_MINUTES = 60;
    private const MAIL_INTERVAL = 120; // seconds between two emails to the same operator

    /** Send the reset link to the active operator with this username or email (silently does nothing otherwise) */
    public static function request(string $login): void
    {
        $login = trim($login);
        if ($login === '' || mb_strlen($login) > 190) {
            return;
        }
        $op = Db::one('SELECT id, username, name, email FROM operators WHERE active = 1 AND (username = ? OR email = ?) LIMIT 1', [$login, mb_strtolower($login)]);
        if (!$op) {
            return;
        }
        if (Db::value('SELECT 1 FROM operator_tokens WHERE operator_id = ? AND created_at > NOW() - INTERVAL ? SECOND', [(int) $op['id'], self::MAIL_INTERVAL])) {
            return;
        }
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        Db::exec('DELETE FROM operator_tokens WHERE operator_id = ?', [(int) $op['id']]);
        Db::exec(
            'INSERT INTO operator_tokens (operator_id, token_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL ? MINUTE)',
            [(int) $op['id'], hash('sha256', $token), self::LINK_MINUTES]
        );
        $mail = Notifier::defaultLanguage(static fn (): array => EmailTemplates::render('operator_password_reset', [
            'operator_name' => (string) $op['name'],
            'username'      => (string) $op['username'],
            'password_link' => rtrim((string) config('base_url'), '/') . '/op/password/' . $token,
            'link_minutes'  => (string) self::LINK_MINUTES,
            'ip'            => client_ip(),
            'app_name'      => (string) config('app_name', 'Bhelpdesk'),
        ]));
        Mailer::send((string) $op['email'], $mail['subject'], $mail['text'], null, ['html' => $mail['html']]);
    }

    /** Operator owning a valid token */
    public static function operatorForToken(string $token): ?array
    {
        if ($token === '' || strlen($token) > 128) {
            return null;
        }
        return Db::one(
            'SELECT o.id, o.username, o.name, o.email FROM operator_tokens t JOIN operators o ON o.id = t.operator_id
              WHERE t.token_hash = ? AND t.expires_at > NOW() AND o.active = 1',
            [hash('sha256', $token)]
        );
    }

    /** @return string|null error message, null when the password is valid */
    public static function check(string $password, string $confirm): ?string
    {
        if (strlen($password) < Operators::MIN_PASSWORD) {
            return __('val.password_short');
        }
        if ($password !== $confirm) {
            return __('val.password_mismatch');
        }
        return null;
    }

    public static function reset(array $op, string $password): void
    {
        Db::exec('UPDATE operators SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), (int) $op['id']]);
        Db::exec('DELETE FROM operator_tokens WHERE operator_id = ?', [(int) $op['id']]);
        TwoFactor::forgetDevices((int) $op['id']);
    }
}
