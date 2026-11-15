<?php
declare(strict_types=1);

namespace Bhelpdesk;

/**
 * Email blacklist (/op/config/blacklist): messages from these addresses or
 * domains are downloaded and logged (status "blocked") but nothing else
 * happens: no ticket, no reply, no notification.
 * A domain also covers its subdomains: example.com blocks a@example.com and
 * a@mail.example.com.
 */
final class Blacklist
{
    public static function all(): array
    {
        return Db::all('SELECT b.*, o.name AS created_by_name FROM email_blacklist b LEFT JOIN operators o ON o.id = b.created_by ORDER BY b.type, b.pattern');
    }

    /**
     * Normalise one entry: "name@example.com" -> email, "example.com" / "@example.com" / "*.example.com" -> domain.
     * @return array{0: string, 1: string}|null [pattern, type]
     */
    public static function normalise(string $s): ?array
    {
        $s = mb_strtolower(trim($s, " \t\r\n<>\"',;"));
        if ($s === '') {
            return null;
        }
        if (str_contains($s, '@') && !str_starts_with($s, '@')) {
            return filter_var($s, FILTER_VALIDATE_EMAIL) && strlen($s) <= 190 ? [$s, 'email'] : null;
        }
        $d = (string) preg_replace('/^(\*\.|@)/', '', $s);
        $d = rtrim($d, '.');
        if (strlen($d) > 190 || !preg_match('/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{0,62}$/', $d)) {
            return null;
        }
        return [$d, 'domain'];
    }

    /**
     * Add one or more entries (one per line / comma separated).
     * @return array{added: list<string>, invalid: list<string>, existing: list<string>}
     */
    public static function add(string $input, string $note, int $operatorId): array
    {
        $out = ['added' => [], 'invalid' => [], 'existing' => []];
        foreach (preg_split('/[\s,;]+/', $input) ?: [] as $raw) {
            if (trim($raw) === '') {
                continue;
            }
            $n = self::normalise($raw);
            if (!$n) {
                $out['invalid'][] = $raw;
                continue;
            }
            if (Db::value('SELECT 1 FROM email_blacklist WHERE pattern = ?', [$n[0]])) {
                $out['existing'][] = $n[0];
                continue;
            }
            Db::exec('INSERT INTO email_blacklist (pattern, type, note, created_by) VALUES (?, ?, ?, ?)', [$n[0], $n[1], $note !== '' ? mb_substr($note, 0, 255) : null, $operatorId]);
            $out['added'][] = $n[0];
        }
        return $out;
    }

    public static function delete(int $id): void
    {
        Db::exec('DELETE FROM email_blacklist WHERE id = ?', [$id]);
    }

    /** Rule matching an address (exact address first, then its domain and parent domains), or null */
    public static function match(string $email): ?array
    {
        $email = mb_strtolower(trim($email));
        $at = strrpos($email, '@');
        if ($at === false) {
            return null;
        }
        $candidates = [$email];
        $labels = explode('.', substr($email, $at + 1));
        for ($i = 0; $i < count($labels) - 1; $i++) {
            $candidates[] = implode('.', array_slice($labels, $i));
        }
        $in = implode(',', array_fill(0, count($candidates), '?'));
        $rows = Db::all("SELECT * FROM email_blacklist WHERE pattern IN ($in)", $candidates);
        if (!$rows) {
            return null;
        }
        usort($rows, static fn (array $a, array $b): int => array_search($a['pattern'], $candidates, true) <=> array_search($b['pattern'], $candidates, true));
        return $rows[0];
    }

    public static function hit(int $id): void
    {
        Db::exec('UPDATE email_blacklist SET hits = hits + 1, last_hit_at = NOW() WHERE id = ?', [$id]);
    }
}
