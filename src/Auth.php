<?php
declare(strict_types=1);

namespace Bhelpdesk;

/** Operator authentication (/op area) */
final class Auth
{
    private static ?array $user = null;

    public static function attempt(string $username, string $password, string $ip): bool
    {
        $op = Db::one('SELECT * FROM operators WHERE username = ? AND active = 1', [$username]);

        // always verify a hash, even for unknown users (avoids a timing leak)
        static $dummy = null;
        $hash = $op['password_hash'] ?? ($dummy ??= password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT));
        if (!password_verify($password, $hash) || !$op) {
            Throttle::hit('op_login_fail', $ip);
            return false;
        }

        if (password_needs_rehash($op['password_hash'], PASSWORD_DEFAULT)) {
            Db::exec('UPDATE operators SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $op['id']]);
        }

        Session::regenerate();
        $_SESSION['op_id'] = (int) $op['id'];
        $_SESSION['op_last'] = time();
        Db::exec('UPDATE operators SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?', [$ip, $op['id']]);
        Throttle::clear('op_login_fail', $ip);
        return true;
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
        $op = Db::one('SELECT id, username, name, email, role FROM operators WHERE id = ? AND active = 1', [$_SESSION['op_id']]);
        if (!$op) {
            self::logout();
            return null;
        }
        return self::$user = $op;
    }

    public static function require(): array
    {
        $u = self::user();
        if (!$u) {
            redirect('/op/login');
        }
        return $u;
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
