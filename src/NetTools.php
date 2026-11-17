<?php
declare(strict_types=1);

namespace Bhelpdesk;

/**
 * Network tools for the operators (menu Tools): mtr, ping, whois, IP info.
 *
 * Commands run without a shell (proc_open with an argument list) on input
 * checked against strict patterns, with a time limit and one run at a time
 * per operator. Server packages: mtr-tiny (or mtr), iputils-ping, whois,
 * dnsutils (dig).
 */
final class NetTools
{
    public const TOOLS = ['mtr', 'ping', 'whois', 'ipinfo', 'dns'];

    /** Record types of the DNS query tool ('ALL': the common ones one after the other) */
    public const DNS_TYPES = ['A', 'AAAA', 'CNAME', 'MX', 'NS', 'TXT', 'SOA', 'PTR', 'SRV', 'CAA', 'ALL'];

    /** Public resolvers suggested in the DNS server field */
    public const DNS_SERVERS = ['8.8.8.8' => 'Google', '1.1.1.1' => 'Cloudflare', '9.9.9.9' => 'Quad9', '208.67.222.222' => 'OpenDNS'];

    private const MAX_OUTPUT = 200000;

    /** IPv4 / IPv6 address, or null */
    public static function ip(string $q): ?string
    {
        $q = trim($q, " \t[]");
        return filter_var($q, FILTER_VALIDATE_IP) !== false ? $q : null;
    }

    /** Host name (letters, digits, dots, dashes; never starting with "-"), lower case, or null */
    public static function hostname(string $q): ?string
    {
        $q = strtolower(rtrim(trim($q), '.'));
        if (preg_match('#^[a-z][a-z0-9+.-]*://#', $q)) {
            $q = (string) parse_url($q, PHP_URL_HOST); // a URL was pasted
        }
        if (function_exists('idn_to_ascii') && preg_match('/[^\x20-\x7e]/', $q)) {
            $q = (string) idn_to_ascii($q, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        }
        $label = '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?';
        return strlen($q) <= 253 && preg_match('/^' . $label . '(?:\.' . $label . ')*$/', $q) ? $q : null;
    }

    /** IP or host name for mtr / ping */
    public static function target(string $q): ?string
    {
        return self::ip($q) ?? self::hostname($q);
    }

    /** Domain for whois: at least one dot, "www." is kept (the registry answers for the parent anyway) */
    public static function domain(string $q): ?string
    {
        $h = self::hostname($q);
        return $h !== null && str_contains($h, '.') ? $h : null;
    }

    /** DNS name: like a host name but labels may contain "_" (_dmarc, _sip._tcp, selector._domainkey) */
    public static function dnsName(string $q): ?string
    {
        $q = strtolower(rtrim(trim($q), '.'));
        if (preg_match('#^[a-z][a-z0-9+.-]*://#', $q)) {
            $q = (string) parse_url($q, PHP_URL_HOST);
        }
        if (function_exists('idn_to_ascii') && preg_match('/[^\x20-\x7e]/', $q)) {
            $q = (string) idn_to_ascii($q, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        }
        $label = '[a-z0-9_](?:[a-z0-9_-]{0,61}[a-z0-9_])?';
        return strlen($q) <= 253 && preg_match('/^' . $label . '(?:\.' . $label . ')*$/', $q) ? $q : null;
    }

    /**
     * DNS query with dig, optionally to a given server ('' = the server's resolver,
     * 'auth' = the first authoritative name server of the zone).
     * @return array{server: string, runs: list<array{type: string, status: ?string, answers: list<array>, result: array}>}
     */
    public static function dns(string $name, string $type, string $server): array
    {
        if ($server === 'auth') {
            $server = self::authoritative($name) ?? '';
        }
        $types = $type === 'ALL' ? ['A', 'AAAA', 'CNAME', 'MX', 'NS', 'TXT', 'SOA', 'CAA'] : [$type];
        $runs = [];
        foreach ($types as $t) {
            $cmd = ['dig', '+nocmd', '+nostats', '+time=3', '+tries=2'];
            if ($server !== '') {
                $cmd[] = '@' . $server;
            }
            if ($t === 'PTR' && self::ip($name) !== null) {
                array_push($cmd, '-x', $name); // reverse of an IP
            } else {
                array_push($cmd, $name, $t);
            }
            $res = self::run($cmd, 20);
            $runs[] = ['type' => $t, 'status' => self::digStatus($res['output']), 'answers' => self::digAnswers($res['output']), 'result' => $res];
        }
        return ['server' => $server, 'runs' => $runs];
    }

    /** First name server of the zone of $name (walking up the labels), as a name dig can use */
    private static function authoritative(string $name): ?string
    {
        $labels = explode('.', $name);
        while (count($labels) >= 2) {
            $out = self::run(['dig', '+short', '+time=3', '+tries=2', implode('.', $labels), 'NS'], 15)['output'];
            foreach (preg_split('/\R/', $out) as $line) {
                $ns = self::hostname(rtrim(trim($line), '.'));
                if ($ns !== null) {
                    return $ns;
                }
            }
            array_shift($labels);
        }
        return null;
    }

    private static function digStatus(string $out): ?string
    {
        return preg_match('/status: ([A-Z]+)/', $out, $m) ? $m[1] : null;
    }

    /** Records of the ANSWER SECTION: name, ttl, type, data */
    private static function digAnswers(string $out): array
    {
        if (!preg_match('/;; ANSWER SECTION:\R(.*?)(?:\R\R|\R;;|$)/s', $out, $m)) {
            return [];
        }
        $rows = [];
        foreach (preg_split('/\R/', trim($m[1])) as $line) {
            if (preg_match('/^(\S+)\s+(\d+)\s+IN\s+(\S+)\s+(.*)$/', $line, $r)) {
                $rows[] = ['name' => rtrim($r[1], '.'), 'ttl' => (int) $r[2], 'type' => $r[3], 'data' => trim($r[4])];
            }
        }
        return $rows;
    }

    public static function mtr(string $target): array
    {
        return self::run(['mtr', '--report', '--report-wide', '--show-ips', '--report-cycles', '5', $target], 90);
    }

    public static function ping(string $target): array
    {
        return self::run(['ping', '-c', '4', '-W', '2', $target], 30);
    }

    public static function whois(string $domainOrIp): array
    {
        return self::run(['whois', $domainOrIp], 30);
    }

    /**
     * Everything about an IP (or the IPs of a host name): reverse DNS, AS
     * number and name, announced prefix, country, registry (Team Cymru DNS
     * service), plus the main lines of the RIR whois.
     * @return list<array<string, mixed>>
     */
    public static function ipInfo(string $q): array
    {
        $ips = ($ip = self::ip($q)) !== null ? [$ip] : self::resolve((string) self::hostname($q));
        $out = [];
        foreach (array_slice($ips, 0, 4) as $ip) {
            $row = ['ip' => $ip, 'version' => str_contains($ip, ':') ? 6 : 4, 'ptr' => self::ptr($ip)];
            $row += self::asn($ip);
            // private / reserved addresses: nothing to ask the registries
            $public = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
            $w = $public ? self::whois($ip) : ['output' => ''];
            $row['private'] = !$public;
            $row['whois'] = $w['output'];
            $row['whois_fields'] = self::whoisFields($w['output']);
            $out[] = $row;
        }
        return $out;
    }

    /** A and AAAA records of a host */
    public static function resolve(string $host): array
    {
        if ($host === '') {
            return [];
        }
        $ips = [];
        // A and AAAA separately: with A + AAAA one failing query loses both
        foreach ([DNS_A, DNS_AAAA] as $type) {
            foreach ((array) @dns_get_record($host, $type) as $r) {
                $ips[] = $r['ip'] ?? $r['ipv6'] ?? null;
            }
        }
        return array_values(array_unique(array_filter($ips)));
    }

    private static function ptr(string $ip): ?string
    {
        $names = array_column((array) @dns_get_record(self::reverseName($ip) . '.' . (str_contains($ip, ':') ? 'ip6.arpa' : 'in-addr.arpa'), DNS_PTR), 'target');
        return $names ? implode(', ', $names) : null;
    }

    /** Nibbles / octets in reverse order, as used by the reverse zones and by Team Cymru */
    private static function reverseName(string $ip): string
    {
        if (str_contains($ip, ':')) {
            $hex = bin2hex((string) inet_pton($ip));
            return implode('.', array_reverse(str_split($hex)));
        }
        return implode('.', array_reverse(explode('.', $ip)));
    }

    /** AS, prefix, country, registry, allocation date and AS name from the Team Cymru DNS service */
    private static function asn(string $ip): array
    {
        $zone = str_contains($ip, ':') ? 'origin6.asn.cymru.com' : 'origin.asn.cymru.com';
        $txt = (array) @dns_get_record(self::reverseName($ip) . '.' . $zone, DNS_TXT);
        if (!$txt || empty($txt[0]['txt'])) {
            return ['asn' => null];
        }
        // "15169 | 8.8.8.0/24 | US | arin | 2023-12-28"
        $f = array_map('trim', explode('|', (string) $txt[0]['txt']));
        $asn = explode(' ', $f[0])[0];
        $name = null;
        $as = (array) @dns_get_record('AS' . $asn . '.asn.cymru.com', DNS_TXT);
        if ($as && !empty($as[0]['txt'])) {
            // "15169 | US | arin | 2000-03-30 | GOOGLE, US"
            $parts = array_map('trim', explode('|', (string) $as[0]['txt']));
            $name = $parts[4] ?? null;
        }
        return [
            'asn'       => $asn,
            'as_name'   => $name,
            'prefix'    => $f[1] ?? null,
            'country'   => $f[2] ?? null,
            'registry'  => isset($f[3]) ? strtoupper($f[3]) : null,
            'allocated' => $f[4] ?? null,
        ];
    }

    /** Country name in the current language when the intl extension is there */
    public static function countryName(?string $cc): string
    {
        if (!$cc) {
            return '—';
        }
        if (class_exists(\Locale::class)) {
            $n = \Locale::getDisplayRegion('-' . $cc, Lang::current());
            if ($n !== '' && $n !== $cc) {
                return $n . ' (' . $cc . ')';
            }
        }
        return $cc;
    }

    /** Most useful lines of an IP whois (any RIR) */
    private static function whoisFields(string $whois): array
    {
        $keys = ['inetnum', 'inet6num', 'netrange', 'cidr', 'netname', 'descr', 'org-name', 'orgname', 'organization', 'country', 'abuse-mailbox', 'orgabuseemail', 'origin', 'originas'];
        $out = [];
        foreach (preg_split('/\R/', $whois) as $line) {
            if (preg_match('/^([A-Za-z-]+):\s*(\S.*)$/', $line, $m)) {
                $k = strtolower($m[1]);
                if (in_array($k, $keys, true) && count($out[$k] ?? []) < 3 && !in_array(trim($m[2]), $out[$k] ?? [], true)) {
                    $out[$k][] = trim($m[2]);
                }
            }
        }
        return $out;
    }

    /**
     * Runs a command (no shell) with a time limit.
     * @param list<string> $cmd
     * @return array{ok: bool, output: string, error: ?string, seconds: float}
     */
    public static function run(array $cmd, int $timeout): array
    {
        $start = microtime(true);
        if (!function_exists('proc_open')) {
            return ['ok' => false, 'output' => '', 'error' => __('tools.err_proc_open'), 'seconds' => 0.0];
        }
        // the command is looked up in PATH by proc_open itself (open_basedir hides /usr/bin from is_file)
        $bin = $cmd[0];
        $proc = @proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['PATH' => '/usr/sbin:/usr/bin:/sbin:/bin', 'LANG' => 'C']);
        if (!is_resource($proc)) {
            return ['ok' => false, 'output' => '', 'error' => __('tools.err_run', ['cmd' => $bin]), 'seconds' => 0.0];
        }
        fclose($pipes[0]); // no input (a pipe: open_basedir does not allow /dev/null)
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $out = '';
        $timedOut = false;
        $exit = null;
        while (true) {
            $r = [$pipes[1], $pipes[2]];
            $w = $e = null;
            if (@stream_select($r, $w, $e, 1) === false) {
                break;
            }
            foreach ($r as $p) {
                $out .= (string) fread($p, 65536);
            }
            if (strlen($out) > self::MAX_OUTPUT) {
                $out = substr($out, 0, self::MAX_OUTPUT) . "\n[…]";
                proc_terminate($proc, 9);
                break;
            }
            $st = proc_get_status($proc);
            if (!$st['running']) {
                $exit = (int) $st['exitcode'];
                $out .= (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
                break;
            }
            if (microtime(true) - $start > $timeout) {
                proc_terminate($proc, 9);
                $timedOut = true;
                break;
            }
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
        if ($exit === 127) { // command not found
            return ['ok' => false, 'output' => '', 'error' => __('tools.err_missing', ['cmd' => $bin]), 'seconds' => 0.0];
        }
        return [
            'ok'      => !$timedOut,
            'output'  => rtrim(mb_convert_encoding($out, 'UTF-8', 'UTF-8')),
            'error'   => $timedOut ? __('tools.err_timeout', ['s' => $timeout]) : null,
            'seconds' => round(microtime(true) - $start, 1),
        ];
    }

    /** One tool at a time per operator (mtr can take a minute); false if one is already running */
    public static function lock(int $operatorId): mixed
    {
        $f = @fopen(sys_get_temp_dir() . '/bhelpdesk-tools-' . $operatorId . '.lock', 'c');
        if (!$f || !flock($f, LOCK_EX | LOCK_NB)) {
            return false;
        }
        return $f;
    }
}
