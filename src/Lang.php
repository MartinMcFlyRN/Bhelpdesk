<?php
declare(strict_types=1);

namespace Bhelpdesk;

/**
 * Translations.
 *
 * - One PHP file per language in lang/<code>.php returning ['key' => 'text'].
 * - lang/en.php is the base language and the fallback for missing keys.
 * - Language choice: ?lang=xx (saved in a cookie) > cookie > browser
 *   Accept-Language > config default_language.
 */
final class Lang
{
    public const BASE = 'en';
    private const COOKIE = 'bhd_lang';

    private static string $current = self::BASE;
    /** @var array<string, array<string, string>> */
    private static array $strings = [];

    public static function init(): void
    {
        $available = array_keys(self::available());
        $pick = null;

        if (PHP_SAPI === 'cli') {
            $pick = getenv('BHD_LANG') ?: null;
        } else {
            $requested = isset($_GET['lang']) ? (string) $_GET['lang'] : null;
            if ($requested !== null && in_array($requested, $available, true)) {
                setcookie(self::COOKIE, $requested, [
                    'expires'  => time() + 31536000,
                    'path'     => '/',
                    'secure'   => (bool) config('cookie_secure', true),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
                // reload the same page without ?lang= in the URL
                $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
                $path = (string) parse_url($uri, PHP_URL_PATH);
                parse_str((string) parse_url($uri, PHP_URL_QUERY), $qs);
                unset($qs['lang']);
                header('Location: ' . $path . ($qs ? '?' . http_build_query($qs) : ''), true, 302);
                exit;
            }
            $pick = $_COOKIE[self::COOKIE] ?? null;
            if (!is_string($pick) || !in_array($pick, $available, true)) {
                $pick = self::fromAcceptLanguage((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), $available);
            }
        }

        if (!is_string($pick) || !in_array($pick, $available, true)) {
            $pick = (string) config('default_language', self::BASE);
        }
        self::$current = in_array($pick, $available, true) ? $pick : self::BASE;
    }

    public static function current(): string
    {
        return self::$current;
    }

    /**
     * Languages that have a file in lang/ (only the enabled ones unless $all).
     * @return array<string, string> code => native name
     */
    public static function available(bool $all = false): array
    {
        static $cache = [];
        if (isset($cache[(int) $all])) {
            return $cache[(int) $all];
        }
        $enabled = $all ? [] : (array) config('enabled_languages', []);
        $list = [];
        foreach (glob(BHD_ROOT . '/lang/*.php') ?: [] as $file) {
            $code = basename($file, '.php');
            if (!preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $code)) {
                continue;
            }
            if ($enabled && $code !== self::BASE && !in_array($code, $enabled, true)) {
                continue;
            }
            $list[$code] = (string) (self::load($code)['lang.name'] ?? $code);
        }
        ksort($list);
        return $cache[(int) $all] = $list;
    }

    private static function load(string $code): array
    {
        if (!isset(self::$strings[$code])) {
            $file = BHD_ROOT . '/lang/' . $code . '.php';
            $data = is_file($file) ? require $file : [];
            self::$strings[$code] = is_array($data) ? $data : [];
        }
        return self::$strings[$code];
    }

    public static function get(string $key, array $params = []): string
    {
        $text = self::load(self::$current)[$key] ?? self::load(self::BASE)[$key] ?? $key;
        if ($params) {
            // longest placeholders first, so :name is not replaced inside :names
            uksort($params, static fn ($a, $b) => strlen((string) $b) <=> strlen((string) $a));
            foreach ($params as $k => $v) {
                $text = str_replace(':' . $k, (string) $v, $text);
            }
        }
        return $text;
    }

    private static function fromAcceptLanguage(string $header, array $available): ?string
    {
        $best = null;
        $bestQ = 0.0;
        foreach (explode(',', $header) as $part) {
            $bits = explode(';', trim($part));
            $tag = strtolower(trim($bits[0]));
            $q = 1.0;
            if (isset($bits[1]) && str_starts_with(trim($bits[1]), 'q=')) {
                $q = (float) substr(trim($bits[1]), 2);
            }
            $code = substr($tag, 0, 2);
            if ($q > $bestQ && in_array($code, $available, true)) {
                $best = $code;
                $bestQ = $q;
            }
        }
        return $best;
    }
}
