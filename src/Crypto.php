<?php
declare(strict_types=1);

namespace Bhelpdesk;

use RuntimeException;

/**
 * Symmetric encryption for secrets stored in the database (SMTP/POP3 passwords).
 *
 * libsodium secretbox (XSalsa20-Poly1305). The 32-byte key lives in
 * storage/app.key (created on first use, mode 0600), NOT in the database:
 * a database dump alone does not reveal the passwords.
 * Keep storage/app.key in your backups: without it saved passwords are lost.
 */
final class Crypto
{
    private const PREFIX = 'v1:';

    private static function key(): string
    {
        static $key = null;
        if ($key !== null) {
            return $key;
        }
        $file = rtrim((string) (config('storage_path') ?: BHD_ROOT . '/storage'), '/') . '/app.key';
        if (is_file($file)) {
            $key = base64_decode(trim((string) file_get_contents($file)), true) ?: '';
        } else {
            $key = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
            $old = umask(0077);
            $ok = @file_put_contents($file, base64_encode($key) . "\n", LOCK_EX);
            umask($old);
            if ($ok === false) {
                throw new RuntimeException('Cannot create ' . $file . ': storage/ must be writable by PHP');
            }
        }
        if (strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('Invalid key in ' . $file);
        }
        return $key;
    }

    public static function encrypt(string $plain): string
    {
        if ($plain === '') {
            return '';
        }
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return self::PREFIX . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, self::key()));
    }

    public static function decrypt(?string $stored): string
    {
        if ($stored === null || $stored === '' || !str_starts_with($stored, self::PREFIX)) {
            return '';
        }
        $raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return '';
        }
        $plain = sodium_crypto_secretbox_open(
            substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            self::key()
        );
        return $plain === false ? '' : $plain;
    }
}
