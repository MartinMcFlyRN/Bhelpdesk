<?php
declare(strict_types=1);

namespace Bhelpdesk;

/**
 * Each area has its own session (different cookie name and path):
 * the customer session is never visible in /op and vice versa.
 */
final class Session
{
    public static function start(string $name, string $cookiePath): void
    {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name($name);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => $cookiePath,
            'secure'   => (bool) config('cookie_secure', true),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 3600,
            'path'     => $p['path'],
            'secure'   => $p['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_destroy();
    }
}
