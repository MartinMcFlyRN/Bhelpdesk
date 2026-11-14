<?php
declare(strict_types=1);

namespace Bhelpdesk;

/**
 * Minimal MIME parser for incoming email (RFC 5322, 2045-2047, 2231), no dependencies.
 *
 * Mime::parse($raw) returns:
 *  - headers:     lower-case name => list of raw (unfolded) values
 *  - text / html: first text/plain and text/html body parts, decoded to UTF-8 (or null)
 *  - attachments: list of [name, mime, data, cid, inline]
 *  - report:      true for delivery/read reports (multipart/report)
 */
final class Mime
{
    private const MAX_PARTS = 200;
    private const MAX_DEPTH = 12;

    private array $headers = [];
    private ?string $text = null;
    private ?string $html = null;
    private array $attachments = [];
    private bool $report = false;
    private int $parts = 0;

    public static function parse(string $raw): self
    {
        $m = new self();
        $raw = str_replace("\r\n", "\n", $raw);
        [$head, $body] = self::split($raw);
        $m->headers = self::headers($head);
        $m->walk($m->headers, $body, 0);
        return $m;
    }

    // ---------- accessors ----------

    /** First value of a header, RFC 2047 decoded to UTF-8 ('' when missing) */
    public function header(string $name): string
    {
        $v = $this->headers[strtolower($name)][0] ?? '';
        return $v === '' ? '' : self::decodeWords($v);
    }

    public function rawHeader(string $name): string
    {
        return $this->headers[strtolower($name)][0] ?? '';
    }

    public function has(string $name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    public function text(): ?string
    {
        return $this->text;
    }

    public function html(): ?string
    {
        return $this->html;
    }

    /** @return list<array{name: string, mime: string, data: string, cid: string, inline: bool}> */
    public function attachments(): array
    {
        return $this->attachments;
    }

    public function isReport(): bool
    {
        return $this->report;
    }

    /** @return list<array{email: string, name: string}> addresses of a header (From, To, Cc, Reply-To) */
    public function addresses(string $header): array
    {
        $out = [];
        foreach ($this->headers[strtolower($header)] ?? [] as $value) {
            foreach (self::addressList($value) as $a) {
                $out[] = $a;
            }
        }
        return $out;
    }

    /** @return list<string> message ids (without <>) found in Message-ID / In-Reply-To / References */
    public function ids(string $header): array
    {
        preg_match_all('/<([^<>\s]{1,250})>/', implode(' ', $this->headers[strtolower($header)] ?? []), $m);
        return array_values(array_unique($m[1]));
    }

    // ---------- parsing ----------

    /** @return array{0: string, 1: string} headers block, body */
    private static function split(string $s): array
    {
        $p = strpos($s, "\n\n");
        if ($p === false) {
            return [$s, ''];
        }
        return [substr($s, 0, $p), substr($s, $p + 2)];
    }

    private static function headers(string $head): array
    {
        $head = (string) preg_replace("/\n[ \t]+/", ' ', $head);
        $out = [];
        foreach (explode("\n", $head) as $line) {
            $p = strpos($line, ':');
            if ($p === false || $p === 0) {
                continue;
            }
            $name = strtolower(trim(substr($line, 0, $p)));
            $out[$name][] = trim(substr($line, $p + 1));
        }
        return $out;
    }

    private function walk(array $headers, string $body, int $depth): void
    {
        if (++$this->parts > self::MAX_PARTS || $depth > self::MAX_DEPTH) {
            return;
        }
        [$type, $params] = self::contentType($headers['content-type'][0] ?? 'text/plain');
        [$disposition, $dparams] = self::contentType($headers['content-disposition'][0] ?? '');
        $encoding = strtolower(trim($headers['content-transfer-encoding'][0] ?? '7bit'));

        if (str_starts_with($type, 'multipart/')) {
            if ($type === 'multipart/report') {
                $this->report = true;
            }
            $boundary = $params['boundary'] ?? '';
            if ($boundary === '') {
                return;
            }
            foreach (self::multipart($body, $boundary) as $part) {
                [$h, $b] = self::split($part);
                $this->walk(self::headers($h), $b, $depth + 1);
            }
            return;
        }

        $data = self::decodeBody($body, $encoding);
        $name = $dparams['filename'] ?? $params['name'] ?? '';
        $name = $name !== '' ? self::decodeWords($name) : '';
        $isAttachment = $disposition === 'attachment' || $name !== '';

        if (!$isAttachment && $type === 'text/plain' && $this->text === null) {
            $this->text = self::toUtf8($data, $params['charset'] ?? '');
            return;
        }
        if (!$isAttachment && $type === 'text/html' && $this->html === null) {
            $this->html = self::toUtf8($data, $params['charset'] ?? '');
            return;
        }
        if (!$isAttachment && $type === 'text/plain') {
            // further inline text parts (e.g. text around an inline image): append
            $this->text .= "\n" . self::toUtf8($data, $params['charset'] ?? '');
            return;
        }
        if (!$isAttachment && str_starts_with($type, 'text/')) {
            return; // calendar, enriched text...: ignored when not an attachment
        }
        if ($type === 'message/delivery-status' || $type === 'message/disposition-notification') {
            $this->report = true;
            return;
        }
        if ($name === '') {
            $name = $type === 'message/rfc822' ? 'message.eml' : 'file';
        }
        $this->attachments[] = [
            'name'   => $name,
            'mime'   => $type,
            'data'   => $data,
            'cid'    => trim($headers['content-id'][0] ?? '', " <>"),
            'inline' => $disposition === 'inline' || ($disposition === '' && isset($headers['content-id'])),
        ];
    }

    /** @return list<string> */
    private static function multipart(string $body, string $boundary): array
    {
        $parts = [];
        $current = null;
        foreach (explode("\n", $body) as $line) {
            $t = rtrim($line, " \t");
            if ($t === '--' . $boundary . '--') {
                break;
            }
            if ($t === '--' . $boundary) {
                if ($current !== null) {
                    $parts[] = $current;
                }
                $current = '';
                continue;
            }
            if ($current !== null) {
                $current .= $line . "\n";
            }
        }
        if ($current !== null && $current !== '') {
            $parts[] = $current;
        }
        return array_map(static fn (string $p): string => substr($p, -1) === "\n" ? substr($p, 0, -1) : $p, $parts);
    }

    /**
     * "type/subtype; a=b; c*=utf-8''x" -> [type, params], with RFC 2231
     * (encoded and continued) parameters.
     * @return array{0: string, 1: array<string, string>}
     */
    private static function contentType(string $value): array
    {
        $chunks = self::splitParams($value);
        $type = strtolower(trim(array_shift($chunks) ?? ''));
        $params = [];
        $continued = [];
        foreach ($chunks as $c) {
            $p = strpos($c, '=');
            if ($p === false) {
                continue;
            }
            $k = strtolower(trim(substr($c, 0, $p)));
            $v = trim(substr($c, $p + 1));
            if (strlen($v) >= 2 && $v[0] === '"' && substr($v, -1) === '"') {
                $v = stripcslashes(substr($v, 1, -1));
            }
            if (preg_match('/^([a-z0-9_-]+)\*(\d+)?(\*)?$/', $k, $m)) {
                // name*=charset'lang'value  or  name*0*=...  name*1=...
                $continued[$m[1]][(int) ($m[2] ?? 0)] = [$v, isset($m[3]) || !isset($m[2])];
                continue;
            }
            $params[$k] = $v;
        }
        foreach ($continued as $k => $pieces) {
            ksort($pieces);
            $charset = '';
            $out = '';
            foreach ($pieces as $i => [$v, $encoded]) {
                if ($encoded) {
                    if ($i === array_key_first($pieces) && preg_match("/^([^']*)'[^']*'(.*)$/s", $v, $m)) {
                        $charset = $m[1];
                        $v = $m[2];
                    }
                    $v = rawurldecode($v);
                }
                $out .= $v;
            }
            $params[$k] = self::toUtf8($out, $charset);
        }
        return [$type, $params];
    }

    /** Split on ';' outside double quotes */
    private static function splitParams(string $s): array
    {
        $out = [];
        $cur = '';
        $q = false;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $ch = $s[$i];
            if ($ch === '\\' && $q && $i + 1 < $len) {
                $cur .= $ch . $s[++$i];
                continue;
            }
            if ($ch === '"') {
                $q = !$q;
            }
            if ($ch === ';' && !$q) {
                $out[] = $cur;
                $cur = '';
                continue;
            }
            $cur .= $ch;
        }
        $out[] = $cur;
        return $out;
    }

    private static function decodeBody(string $body, string $encoding): string
    {
        return match ($encoding) {
            'base64'           => (string) base64_decode((string) preg_replace('/[^A-Za-z0-9+\/=]/', '', $body)),
            'quoted-printable' => quoted_printable_decode(str_replace("\n", "\r\n", $body)),
            default            => $body,
        };
    }

    /** Text in any charset -> valid UTF-8 */
    public static function toUtf8(string $s, string $charset): string
    {
        $charset = strtoupper(trim($charset, " \"'"));
        $aliases = ['' => '', 'US-ASCII' => 'UTF-8', 'ASCII' => 'UTF-8', 'UTF8' => 'UTF-8', 'LATIN1' => 'ISO-8859-1', 'CP1252' => 'Windows-1252', 'WINDOWS-1252' => 'Windows-1252'];
        $charset = $aliases[$charset] ?? $charset;
        if ($charset === '' || $charset === 'UTF-8') {
            if (mb_check_encoding($s, 'UTF-8')) {
                return $s;
            }
            $charset = 'Windows-1252'; // undeclared 8-bit text: the most common case
        }
        $out = null;
        if (in_array(strtolower($charset), array_map('strtolower', mb_list_encodings()), true)) {
            $out = @mb_convert_encoding($s, 'UTF-8', $charset);
        }
        if (!is_string($out)) {
            $out = @iconv($charset, 'UTF-8//IGNORE', $s);
        }
        return mb_scrub(is_string($out) ? $out : $s, 'UTF-8');
    }

    /** RFC 2047 encoded words -> UTF-8 */
    public static function decodeWords(string $s): string
    {
        if (!str_contains($s, '=?')) {
            return self::toUtf8($s, '');
        }
        // blanks between two encoded words are not part of the text
        $s = (string) preg_replace('/(\?=)\s+(=\?)/', '$1$2', $s);
        return (string) preg_replace_callback('/=\?([^?]+)\?([QqBb])\?([^?]*)\?=/', static function (array $m): string {
            $charset = (string) preg_replace('/\*.*$/', '', $m[1]); // drop RFC 2231 language
            $data = strtoupper($m[2]) === 'B'
                ? (string) base64_decode($m[3])
                : quoted_printable_decode(str_replace('_', ' ', $m[3]));
            return self::toUtf8($data, $charset);
        }, $s);
    }

    /** @return list<array{email: string, name: string}> */
    public static function addressList(string $value): array
    {
        $out = [];
        $cur = '';
        $q = false;
        $angle = false;
        $len = strlen($value);
        for ($i = 0; $i < $len; $i++) {
            $ch = $value[$i];
            if ($ch === '"' && ($i === 0 || $value[$i - 1] !== '\\')) {
                $q = !$q;
            } elseif (!$q && $ch === '<') {
                $angle = true;
            } elseif (!$q && $ch === '>') {
                $angle = false;
            }
            if (($ch === ',' || $ch === ';') && !$q && !$angle) {
                $out[] = $cur;
                $cur = '';
                continue;
            }
            $cur .= $ch;
        }
        $out[] = $cur;

        $list = [];
        foreach ($out as $a) {
            $a = trim($a);
            if ($a === '') {
                continue;
            }
            if (preg_match('/^(.*)<([^<>]+)>\s*$/s', $a, $m)) {
                $name = trim($m[1]);
                $email = trim($m[2]);
            } else {
                $name = '';
                $email = trim((string) preg_replace('/\(.*\)/', '', $a));
            }
            $name = trim(self::decodeWords(trim($name, " \t\"")), " \t\"'");
            $email = mb_strtolower($email);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $list[] = ['email' => $email, 'name' => $name];
            }
        }
        return $list;
    }
}
