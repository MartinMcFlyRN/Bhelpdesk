<?php
declare(strict_types=1);

namespace Bhelpdesk;

use RuntimeException;

/**
 * Minimal SMTP client (RFC 5321) without dependencies.
 * Security: 'ssl' (implicit TLS, usually port 465), 'starttls' (usually 587),
 * 'none' (plain, only for local relays). Authentication: PLAIN or LOGIN.
 */
final class Smtp
{
    /** @var resource|null */
    private $sock = null;
    private array $log = [];

    public function __construct(
        private string $host,
        private int $port,
        private string $security = 'starttls',
        private string $user = '',
        private string $pass = '',
        private bool $verifyTls = true,
        private int $timeout = 20,
    ) {
    }

    /** Last SMTP dialogue (passwords redacted), useful to show test results */
    public function log(): array
    {
        return $this->log;
    }

    /**
     * Send a raw RFC 5322 message (headers + body, CRLF line endings).
     * @param list<string> $recipients
     */
    public function send(string $from, array $recipients, string $message): void
    {
        $this->connect();
        try {
            $this->cmd('MAIL FROM:<' . $from . '>', [250]);
            foreach ($recipients as $rcpt) {
                $this->cmd('RCPT TO:<' . $rcpt . '>', [250, 251]);
            }
            $this->cmd('DATA', [354]);
            // dot-stuffing: lines starting with "." get an extra "."
            $data = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $message));
            $data = str_replace("\n", "\r\n", rtrim((string) $data, "\n")) . "\r\n.";
            $this->cmd($data, [250], 'DATA <message>');
            $this->cmd('QUIT', [221]);
        } finally {
            $this->close();
        }
    }

    /** Connect and authenticate only (connection test) */
    public function test(): void
    {
        $this->connect();
        try {
            $this->cmd('QUIT', [221]);
        } finally {
            $this->close();
        }
    }

    private function connect(): void
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
        $this->expect([220]);
        $ehlo = $this->ehlo();
        if ($this->security === 'starttls') {
            if (!preg_match('/^250[ -]STARTTLS/mi', $ehlo)) {
                throw new RuntimeException('The server does not offer STARTTLS');
            }
            $this->cmd('STARTTLS', [220]);
            if (!@stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new RuntimeException('TLS negotiation failed (certificate not valid for ' . $this->host . '?)');
            }
            $ehlo = $this->ehlo();
        }
        if ($this->user !== '') {
            if (preg_match('/^250[ -]AUTH[ =].*\bPLAIN\b/mi', $ehlo)) {
                $this->cmd('AUTH PLAIN ' . base64_encode("\0" . $this->user . "\0" . $this->pass), [235], 'AUTH PLAIN <credentials>');
            } else {
                $this->cmd('AUTH LOGIN', [334]);
                $this->cmd(base64_encode($this->user), [334], '<user>');
                $this->cmd(base64_encode($this->pass), [235], '<password>');
            }
        }
    }

    private function ehlo(): string
    {
        $name = preg_replace('/[^A-Za-z0-9.-]/', '', (string) (parse_url((string) config('base_url'), PHP_URL_HOST) ?: gethostname() ?: 'localhost'));
        return $this->cmd('EHLO ' . ($name ?: 'localhost'), [250]);
    }

    private function cmd(string $line, array $ok, ?string $logAs = null): string
    {
        $this->log[] = 'C: ' . ($logAs ?? $line);
        if (@fwrite($this->sock, $line . "\r\n") === false) {
            throw new RuntimeException('Connection lost while sending ' . ($logAs ?? strtok($line, ' ')));
        }
        return $this->expect($ok);
    }

    private function expect(array $ok): string
    {
        $reply = '';
        while (($line = fgets($this->sock, 2048)) !== false) {
            $reply .= $line;
            $this->log[] = 'S: ' . rtrim($line);
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        if ($reply === '') {
            $meta = stream_get_meta_data($this->sock);
            throw new RuntimeException(!empty($meta['timed_out']) ? 'Timeout waiting for the server' : 'Connection closed by the server');
        }
        $code = (int) substr($reply, 0, 3);
        if (!in_array($code, $ok, true)) {
            throw new RuntimeException('SMTP error: ' . trim(preg_replace('/\s+/', ' ', $reply) ?? $reply));
        }
        return $reply;
    }

    private function close(): void
    {
        if ($this->sock) {
            @fclose($this->sock);
            $this->sock = null;
        }
    }
}
