<?php
declare(strict_types=1);

namespace Bhelpdesk;

final class Throttle
{
    private static function limits(string $action): array
    {
        return (array) config("throttle.$action", [10, 900]);
    }

    public static function tooMany(string $action, string $ip): bool
    {
        [$max, $window] = self::limits($action);
        $n = (int) Db::value(
            'SELECT COUNT(*) FROM throttle WHERE action = ? AND ip = ? AND created_at > NOW() - INTERVAL ? SECOND',
            [$action, $ip, (int) $window]
        );
        return $n >= (int) $max;
    }

    public static function hit(string $action, string $ip): void
    {
        Db::exec('INSERT INTO throttle (action, ip) VALUES (?, ?)', [$action, $ip]);
        // pulizia occasionale
        if (random_int(1, 50) === 1) {
            Db::exec('DELETE FROM throttle WHERE created_at < NOW() - INTERVAL 1 DAY');
        }
    }

    public static function clear(string $action, string $ip): void
    {
        Db::exec('DELETE FROM throttle WHERE action = ? AND ip = ?', [$action, $ip]);
    }
}
