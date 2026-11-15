<?php
declare(strict_types=1);

namespace Bhelpdesk;

/** Operator authentication (/op area) */
final class Auth
{
    private static ?array $user = null;

    private const PENDING_TTL = 600; // seconds to complete the second factor after the password

    /**
     * First step: username and password.
     * Returns 'ok' (signed in: trusted computer), 'code' (TOTP code needed),
     * 'setup' (2FA to be set up now) or 'invalid'.
     */
    public static function attempt(string $username, string $password, string $ip): string
    {
        $op = Db::one('SELECT * FROM operators WHERE username = ? AND active = 1', [$username]);

        // always verify a hash, even for unknown users (avoids a timing leak)
        static $dummy = null;
        $hash = $op['password_hash'] ?? ($dummy ??= password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT));
        if (!password_verify($password, $hash) || !$op) {
            Throttle::hit('op_login_fail', $ip);
            return 'invalid';
        }

        if (password_needs_rehash($op['password_hash'], PASSWORD_DEFAULT)) {
            Db::exec('UPDATE operators SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $op['id']]);
        }

        Throttle::clear('op_login_fail', $ip);
        if (TwoFactor::isEnabled($op) && TwoFactor::isTrusted((int) $op['id'])) {
            self::complete((int) $op['id'], $ip);
            return 'ok';
        }
        // password ok: the second factor is still needed (nothing else is accessible)
        Session::regenerate();
        unset($_SESSION['op_id']);
        $_SESSION['op_pending'] = ['id' => (int) $op['id'], 'at' => time()];
        return TwoFactor::isEnabled($op) ? 'code' : 'setup';
    }

    /** Operator who passed the password and still has to give the second factor */
    public static function pending(): ?array
    {
        $p = $_SESSION['op_pending'] ?? null;
        if (!is_array($p) || time() - (int) ($p['at'] ?? 0) > self::PENDING_TTL) {
            unset($_SESSION['op_pending'], $_SESSION['op_2fa_secret']);
            return null;
        }
        return Db::one('SELECT * FROM operators WHERE id = ? AND active = 1', [(int) $p['id']]);
    }

    /** Second factor passed: the operator is signed in */
    public static function complete(int $operatorId, string $ip): void
    {
        Session::regenerate();
        unset($_SESSION['op_pending'], $_SESSION['op_2fa_secret']);
        $_SESSION['op_id'] = $operatorId;
        $_SESSION['op_last'] = time();
        self::$user = null;
        Db::exec('UPDATE operators SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?', [$ip, $operatorId]);
    }

    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        if (empty($_SESSION['op_id'])) {
            return null;
        }
        $idle = (int) config('op_session_idle', 1800);
        if (time() - (int) ($_SESSION['op_last'] ?? 0) > $idle) {
            self::logout();
            return null;
        }
        $_SESSION['op_last'] = time();
        $op = Db::one('SELECT id, username, name, email, role, all_departments FROM operators WHERE id = ? AND active = 1', [$_SESSION['op_id']]);
        if (!$op) {
            self::logout();
            return null;
        }
        return self::$user = $op;
    }

    /** Paths a "billing" operator may open (everything else sends them to Billing) */
    private const BILLING_PATHS = ['/op/billing', '/op/profile', '/op/logout', '/op/customers/lookup', '/op/2fa'];

    public static function require(): array
    {
        $u = self::user();
        if (!$u) {
            redirect('/op/login');
        }
        if ($u['role'] === 'billing') {
            $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
            $ok = false;
            foreach (self::BILLING_PATHS as $p) {
                if ($path === $p || str_starts_with($path, $p . '/')) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                redirect('/op/billing/invoices');
            }
        }
        return $u;
    }

    /** Only "billing" operators mark invoices issued / to issue */
    public static function canIssueInvoices(array $op): bool
    {
        return ($op['role'] ?? '') === 'billing';
    }

    public static function requireAdmin(): array
    {
        $u = self::require();
        if ($u['role'] !== 'admin') {
            http_response_code(403);
            View::render('op', 'error', ['title' => __('error.forbidden.title'), 'message' => __('error.forbidden.message')]);
            exit;
        }
        return $u;
    }

    public static function logout(): void
    {
        self::$user = null;
        Session::destroy();
    }
}
