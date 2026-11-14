<?php
declare(strict_types=1);

namespace Bhelpdesk;

use RuntimeException;

/**
 * Minimal POP3 client (RFC 1939 + STLS RFC 2595) without dependencies.
 * Security: 'ssl' (implicit TLS, port 995), 'starttls' (STLS on 110), 'none'.
 */
final class Pop3
{
    /** @var resource|null */
    private $sock = null;
    /** @var list<string> */
    private array $log = [];

    public function __construct(
        private string $host,
        private int $port,
        private string $security = 'ssl',
        private string $user = '',
        private string $pass = '',
        private bool $verifyTls = true,
        private int $timeout = 20,
    ) {
    }

    /** Last POP3 dialogue (password replaced by its fingerprint), useful to show test results */
    public function log(): array
    {
        return $this->log;
    }

    /**
     * Describes a secret without revealing it: length and first 6 hex chars of its SHA1
     * (the same fingerprint Dovecot logs on "Password mismatch"), plus a warning
     * when it starts/ends with spaces, a frequent copy & paste mistake.
     */
    public static function fingerprint(string $secret): string
    {
        $fp = '<password: ' . strlen($secret) . ' bytes, SHA1 ' . substr(sha1($secret), 0, 6) . '...';
        if ($secret !== trim($secret)) {
            $fp .= ', WARNING: starts or ends with spaces';
        }
        return $fp . '>';
    }

    public function connect(): void
    {
        $ctx = stream_context_create(['ssl' => [
            'verify_peer'       => $this->verifyTls,
            'verify_peer_name'  => $this->verifyTls,
            'allow_self_signed' => !$this->verifyTls,
            'SNI_enabled'       => true,
            'peer_name'         => $this->host,
        ]]);
        $scheme = $this->security === 'ssl' ? 'ssl://' : 'tcp://';
        $errno = 0;
        $errstr = '';
        $sock = @stream_socket_client($scheme . $this->host . ':' . $this->port, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$sock) {
            throw new RuntimeException("Connection to {$this->host}:{$this->port} failed: " . ($errstr ?: 'error ' . $errno));
        }
        stream_set_timeout($sock, $this->timeout);
        $this->sock = $sock;
        $this->ok($this->line(true));
        if ($this->security === 'starttls') {
            $this->cmd('STLS');
            if (!@stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new RuntimeException('TLS negotiation failed (certificate not valid for ' . $this->host . '?)');
            }
        }
        $this->cmd('USER ' . $this->user);
        $this->cmd('PASS ' . $this->pass, 'PASS', 'PASS ' . self::fingerprint($this->pass));
    }

    /** @return array{count: int, size: int} */
    public function stat(): array
    {
        $r = $this->cmd('STAT');
        $p = explode(' ', $r);
        return ['count' => (int) ($p[1] ?? 0), 'size' => (int) ($p[2] ?? 0)];
    }

    /** @return array<int, string> message number => unique id */
    public function uidl(): array
    {
        $this->cmd('UIDL');
        $out = [];
        foreach ($this->multiline() as $l) {
            [$n, $uid] = array_pad(explode(' ', trim($l), 2), 2, '');
            $out[(int) $n] = $uid;
        }
        return $out;
    }

    /** @return array<int, int> message number => size in bytes */
    public function list(): array
    {
        $this->cmd('LIST');
        $out = [];
        foreach ($this->multiline() as $l) {
            [$n, $size] = array_pad(explode(' ', trim($l), 2), 2, '0');
            $out[(int) $n] = (int) $size;
        }
        return $out;
    }

    public function retr(int $n): string
    {
        $this->cmd('RETR ' . $n);
        return implode("\r\n", $this->multiline()) . "\r\n";
    }

    public function dele(int $n): void
    {
        $this->cmd('DELE ' . $n);
    }

    public function quit(): void
    {
        if ($this->sock) {
            try {
                $this->cmd('QUIT');
            } catch (\Throwable) {
            }
            @fclose($this->sock);
            $this->sock = null;
        }
    }

    private function cmd(string $line, ?string $logAs = null, ?string $logLine = null): string
    {
        $this->log[] = 'C: ' . ($logLine ?? $line);
        if (@fwrite($this->sock, $line . "\r\n") === false) {
            throw new RuntimeException('Connection lost while sending ' . ($logAs ?? strtok($line, ' ')));
        }
        return $this->ok($this->line(true), $logAs ?? strtok($line, ' '));
    }

    private function line(bool $log = false): string
    {
        $l = fgets($this->sock, 8192);
        if ($l === false) {
            $meta = stream_get_meta_data($this->sock);
            throw new RuntimeException(!empty($meta['timed_out']) ? 'Timeout waiting for the server' : 'Connection closed by the server');
        }
        $l = rtrim($l, "\r\n");
        if ($log) {
            $this->log[] = 'S: ' . $l;
        }
        return $l;
    }

    private function ok(string $reply, string $what = 'greeting'): string
    {
        if (!str_starts_with($reply, '+OK')) {
            throw new RuntimeException('POP3 error on ' . $what . ': ' . $reply);
        }
        return $reply;
    }

    /** Multi-line response, with dot-unstuffing */
    private function multiline(): array
    {
        $lines = [];
        while (true) {
            $l = $this->line();
            if ($l === '.') {
                break;
            }
            $lines[] = str_starts_with($l, '..') ? substr($l, 1) : $l;
        }
        return $lines;
    }
}
