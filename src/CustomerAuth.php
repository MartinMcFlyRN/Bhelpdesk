<?php
declare(strict_types=1);

namespace Bhelpdesk;

/**
 * Customer accounts for the /customer area.
 *
 * Customers are created when they open a ticket (or by operators) without a
 * password. The first time they try to sign in they receive an email with a
 * link to create one; the same flow is used for "forgot password".
 * Password links are single-use, stored hashed, valid PASSWORD_LINK_MINUTES.
 */
final class CustomerAuth
{
    public const MIN_PASSWORD = 10;
    public const PASSWORD_LINK_MINUTES = 120;
    /** minimum seconds between two password emails to the same customer */
    private const MAIL_INTERVAL = 120;

    private static ?array $user = null;

    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        $id = (int) ($_SESSION['customer_id'] ?? 0);
        if (!$id) {
            return null;
        }
        $c = Customers::find($id);
        // a password change elsewhere invalidates the other sessions
        if (!$c || empty($c['password_hash']) || ($_SESSION['customer_pw'] ?? '') !== self::pwMark($c)) {
            self::logout();
            return null;
        }
        return self::$user = $c;
    }

    /**
     * @return 'ok'|'invalid'|'no_password'
     */
    public static function attempt(string $email, string $password, string $ip): string
    {
        $c = Customers::findByEmail($email);
        static $dummy = null;
        $hash = $c['password_hash'] ?? null;
        if ($c && !$hash) {
            return 'no_password';
        }
        // always verify a hash, even for unknown emails (avoids a timing leak)
        $ok = password_verify($password, $hash ?? ($dummy ??= password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT)));
        if (!$c || !$ok) {
            Throttle::hit('customer_login_fail', $ip);
            return 'invalid';
        }
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            Db::exec('UPDATE customers SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $c['id']]);
            $c = Customers::find((int) $c['id']);
        }
        self::login($c, $ip);
        Throttle::clear('customer_login_fail', $ip);
        return 'ok';
    }

    private static function login(array $c, string $ip): void
    {
        Session::regenerate();
        $_SESSION['customer_id'] = (int) $c['id'];
        $_SESSION['customer_pw'] = self::pwMark($c);
        Db::exec('UPDATE customers SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?', [$ip, $c['id']]);
        self::$user = null;
    }

    /** Short fingerprint of the password hash, kept in the session */
    private static function pwMark(array $c): string
    {
        return substr(hash('sha256', (string) $c['password_hash']), 0, 16);
    }

    public static function logout(): void
    {
        self::$user = null;
        unset($_SESSION['customer_id'], $_SESSION['customer_pw'], $_SESSION['tickets'], $_SESSION['links']);
        Session::regenerate();
    }

    /**
     * Email a single-use link to create (first access) or reset the password.
     * Silently does nothing when the customer got one very recently.
     */
    public static function sendPasswordLink(array $c): bool
    {
        $recent = Db::value(
            'SELECT 1 FROM customer_tokens WHERE customer_id = ? AND created_at > NOW() - INTERVAL ? SECOND',
            [(int) $c['id'], self::MAIL_INTERVAL]
        );
        if ($recent) {
            return true;
        }
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        // only the newest link is valid
        Db::exec('DELETE FROM customer_tokens WHERE customer_id = ?', [(int) $c['id']]);
        Db::exec(
            'INSERT INTO customer_tokens (customer_id, token_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL ? MINUTE)',
            [(int) $c['id'], hash('sha256', $token), self::PASSWORD_LINK_MINUTES]
        );
        $url = rtrim((string) config('base_url'), '/') . '/customer/password/' . $token;
        $first = empty($c['password_hash']);

        return Lang::with((string) ($c['language'] ?? Lang::BASE), static function () use ($c, $url, $first): bool {
            $params = [
                'name'    => $c['name'],
                'app'     => (string) config('app_name', 'Bhelpdesk'),
                'url'     => $url,
                'minutes' => self::PASSWORD_LINK_MINUTES,
            ];
            return Mailer::send(
                (string) $c['email'],
                __($first ? 'mail.pw_setup.subject' : 'mail.pw_reset.subject', $params),
                __($first ? 'mail.pw_setup.body' : 'mail.pw_reset.body', $params)
            );
        });
    }

    /** Customer owning a valid (unused, not expired) password token */
    public static function customerForToken(string $token): ?array
    {
        if ($token === '' || strlen($token) > 128) {
            return null;
        }
        $row = Db::one(
            'SELECT customer_id FROM customer_tokens WHERE token_hash = ? AND expires_at > NOW()',
            [hash('sha256', $token)]
        );
        return $row ? Customers::find((int) $row['customer_id']) : null;
    }

    /** Set the password from a token link, then sign the customer in */
    public static function setPassword(array $c, string $password, string $ip): void
    {
        Db::exec(
            'UPDATE customers SET password_hash = ?, email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?',
            [password_hash($password, PASSWORD_DEFAULT), (int) $c['id']]
        );
        Db::exec('DELETE FROM customer_tokens WHERE customer_id = ?', [(int) $c['id']]);
        self::login(Customers::find((int) $c['id']), $ip);
    }

    public static function validatePassword(string $password, string $confirm): ?string
    {
        if (mb_strlen($password) < self::MIN_PASSWORD) {
            return __('val.customer_password_short', ['min' => self::MIN_PASSWORD]);
        }
        if ($password !== $confirm) {
            return __('val.password_mismatch');
        }
        return null;
    }
}
