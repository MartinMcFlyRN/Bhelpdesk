<?php
declare(strict_types=1);

namespace Bhelpdesk;

/**
 * Time-based one-time passwords (RFC 6238 / RFC 4226): 6 digits, 30 seconds,
 * HMAC-SHA1 - the defaults of Google Authenticator, Microsoft Authenticator,
 * Authy, 1Password, Bitwarden...
 */
final class Totp
{
    public const PERIOD = 30;
    public const DIGITS = 6;
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** New random secret, base32 (160 bits) */
    public static function newSecret(): string
    {
        return self::base32(random_bytes(20));
    }

    /** otpauth:// URI shown as QR code */
    public static function uri(string $secret, string $account, string $issuer): string
    {
        $label = rawurlencode($issuer) . ':' . rawurlencode($account);
        return 'otpauth://totp/' . $label . '?' . http_build_query([
            'secret' => $secret, 'issuer' => $issuer, 'algorithm' => 'SHA1', 'digits' => self::DIGITS, 'period' => self::PERIOD,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** Secret grouped by 4 for typing it by hand */
    public static function pretty(string $secret): string
    {
        return trim(chunk_split($secret, 4, ' '));
    }

    /**
     * Check a code allowing $window steps of clock drift (default 1 = ±30 s).
     * Returns the time step matched, or null. A step <= $lastStep is refused (replay).
     */
    public static function verify(string $secret, string $code, ?int $lastStep = null, int $window = 1, ?int $time = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (!preg_match('/^\d{' . self::DIGITS . '}$/', $code)) {
            return null;
        }
        $key = self::unbase32($secret);
        if ($key === '') {
            return null;
        }
        $now = intdiv($time ?? time(), self::PERIOD);
        for ($i = -$window; $i <= $window; $i++) {
            $step = $now + $i;
            if ($lastStep !== null && $step <= $lastStep) {
                continue;
            }
            if (hash_equals(self::code($key, $step), $code)) {
                return $step;
            }
        }
        return null;
    }

    public static function code(string $key, int $step): string
    {
        $hash = hash_hmac('sha1', pack('N2', 0, $step), $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $bin = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);
        return str_pad((string) ($bin % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32(string $bin): string
    {
        $bits = '';
        foreach (str_split($bin) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }
        return $out;
    }

    public static function unbase32(string $s): string
    {
        $s = strtoupper((string) preg_replace('/[\s=]+/', '', $s));
        $bits = '';
        foreach (str_split($s) as $c) {
            $v = strpos(self::ALPHABET, $c);
            if ($v === false) {
                return '';
            }
            $bits .= str_pad(decbin($v), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }
}
