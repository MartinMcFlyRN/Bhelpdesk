<?php
declare(strict_types=1);

namespace Bhelpdesk;

final class IpGuard
{
    /**
     * IP del client. X-Forwarded-For viene considerato solo se la richiesta
     * arriva da un proxy fidato, altrimenti sarebbe falsificabile.
     */
    public static function clientIp(array $trustedProxies = []): string
    {
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        if (!$trustedProxies || !self::matchesAny($remote, $trustedProxies)) {
            return $remote;
        }
        $xff = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        $chain = array_reverse(array_filter(array_map('trim', explode(',', $xff))));
        foreach ($chain as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                break;
            }
            if (!self::matchesAny($ip, $trustedProxies)) {
                return $ip;
            }
        }
        return $remote;
    }

    public static function matchesAny(string $ip, array $list): bool
    {
        foreach ($list as $entry) {
            if (self::matches($ip, (string) $entry)) {
                return true;
            }
        }
        return false;
    }

    /** Confronta un IP con un indirizzo singolo o un CIDR (IPv4/IPv6) */
    public static function matches(string $ip, string $cidr): bool
    {
        [$net, $bits] = array_pad(explode('/', trim($cidr), 2), 2, null);
        $ipBin  = @inet_pton($ip);
        $netBin = @inet_pton((string) $net);
        if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin)) {
            return false;
        }
        $maxBits = strlen($ipBin) * 8;
        $bits = $bits === null ? $maxBits : (int) $bits;
        if ($bits < 0 || $bits > $maxBits) {
            return false;
        }
        $bytes = intdiv($bits, 8);
        if (strncmp($ipBin, $netBin, $bytes) !== 0) {
            return false;
        }
        $rem = $bits % 8;
        if ($rem === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $rem)) & 0xFF;
        return (ord($ipBin[$bytes]) & $mask) === (ord($netBin[$bytes]) & $mask);
    }
}
