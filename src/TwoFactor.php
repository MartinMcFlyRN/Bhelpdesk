<?php
declare(strict_types=1);

namespace Bhelpdesk;

/**
 * Two-factor authentication of the operators (mandatory for everybody).
 *
 *  - TOTP secret encrypted with Crypto (storage/app.key)
 *  - 10 single-use recovery codes (sha256 only)
 *  - trusted computers: after a successful second factor the computer is
 *    remembered for TRUST_DAYS days with a random cookie (sha256 in the DB)
 * Operators without 2FA set it up right after the password at the next login.
 */
final class TwoFactor
{
    public const TRUST_DAYS = 30;
    public const RECOVERY_CODES = 10;
    private const COOKIE = 'BHD_OP_DEVICE';

    public static function isEnabled(array $op): bool
    {
        return !empty($op['totp_enabled_at']) && !empty($op['totp_secret']);
    }

    public static function enable(int $operatorId, string $secret, int $step): void
    {
        Db::exec(
            'UPDATE operators SET totp_secret = ?, totp_enabled_at = NOW(), totp_last_step = ? WHERE id = ?',
            [Crypto::encrypt($secret), $step, $operatorId]
        );
    }

    /**
     * Check a code: TOTP (6 digits) or a recovery code.
     * @return string|null 'totp', 'recovery' or null when wrong
     */
    public static function verify(int $operatorId, string $code): ?string
    {
        $op = Db::one('SELECT id, totp_secret, totp_last_step FROM operators WHERE id = ?', [$operatorId]);
        if (!$op || empty($op['totp_secret'])) {
            return null;
        }
        $clean = strtolower((string) preg_replace('/[\s-]+/', '', $code));
        if (preg_match('/^\d{6}$/', $clean)) {
            $step = Totp::verify(Crypto::decrypt((string) $op['totp_secret']), $clean, $op['totp_last_step'] !== null ? (int) $op['totp_last_step'] : null);
            if ($step === null) {
                return null;
            }
            Db::exec('UPDATE operators SET totp_last_step = ? WHERE id = ?', [$step, $operatorId]);
            return 'totp';
        }
        if (preg_match('/^[a-z2-7]{10}$/', $clean)) {
            $n = Db::exec(
                'UPDATE operator_recovery_codes SET used_at = NOW() WHERE operator_id = ? AND code_hash = ? AND used_at IS NULL',
                [$operatorId, hash('sha256', $clean)]
            );
            return $n ? 'recovery' : null;
        }
        return null;
    }

    /** New recovery codes (the old ones stop working); returned once, stored hashed */
    public static function newRecoveryCodes(int $operatorId): array
    {
        Db::exec('DELETE FROM operator_recovery_codes WHERE operator_id = ?', [$operatorId]);
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODES; $i++) {
            $raw = strtolower(substr(Totp::newSecret(), 0, 10));
            Db::exec('INSERT INTO operator_recovery_codes (operator_id, code_hash) VALUES (?, ?)', [$operatorId, hash('sha256', $raw)]);
            $codes[] = substr($raw, 0, 5) . '-' . substr($raw, 5);
        }
        return $codes;
    }

    public static function recoveryLeft(int $operatorId): int
    {
        return (int) Db::value('SELECT COUNT(*) FROM operator_recovery_codes WHERE operator_id = ? AND used_at IS NULL', [$operatorId]);
    }

    // ---------- trusted computers ----------

    /** Remember this computer for TRUST_DAYS days */
    public static function trustDevice(int $operatorId): void
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $expires = time() + self::TRUST_DAYS * 86400;
        Db::exec(
            'INSERT INTO operator_trusted_devices (operator_id, token_hash, user_agent, ip, expires_at, last_used_at) VALUES (?, ?, ?, ?, FROM_UNIXTIME(?), NOW())',
            [$operatorId, hash('sha256', $token), mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), client_ip(), $expires]
        );
        setcookie(self::COOKIE, $token, [
            'expires'  => $expires,
            'path'     => '/op/',
            'secure'   => (bool) config('cookie_secure', true),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /** Is this computer trusted by this operator? */
    public static function isTrusted(int $operatorId): bool
    {
        $token = (string) ($_COOKIE[self::COOKIE] ?? '');
        if ($token === '' || strlen($token) > 100) {
            return false;
        }
        $id = Db::value(
            'SELECT id FROM operator_trusted_devices WHERE operator_id = ? AND token_hash = ? AND expires_at > NOW()',
            [$operatorId, hash('sha256', $token)]
        );
        if ($id) {
            Db::exec('UPDATE operator_trusted_devices SET last_used_at = NOW() WHERE id = ?', [(int) $id]);
        }
        return (bool) $id;
    }

    public static function trustedCount(int $operatorId): int
    {
        return (int) Db::value('SELECT COUNT(*) FROM operator_trusted_devices WHERE operator_id = ? AND expires_at > NOW()', [$operatorId]);
    }

    /** Forget the trusted computers (all, or all but the current one) */
    public static function forgetDevices(int $operatorId, bool $keepCurrent = false): void
    {
        $token = (string) ($_COOKIE[self::COOKIE] ?? '');
        if ($keepCurrent && $token !== '') {
            Db::exec('DELETE FROM operator_trusted_devices WHERE operator_id = ? AND token_hash <> ?', [$operatorId, hash('sha256', $token)]);
        } else {
            Db::exec('DELETE FROM operator_trusted_devices WHERE operator_id = ?', [$operatorId]);
        }
        Db::exec('DELETE FROM operator_trusted_devices WHERE expires_at < NOW()');
    }

    /** Remove the 2FA of an operator: it will be set up again at the next login */
    public static function reset(int $operatorId): void
    {
        Db::exec('UPDATE operators SET totp_secret = NULL, totp_enabled_at = NULL, totp_last_step = NULL WHERE id = ?', [$operatorId]);
        Db::exec('DELETE FROM operator_recovery_codes WHERE operator_id = ?', [$operatorId]);
        Db::exec('DELETE FROM operator_trusted_devices WHERE operator_id = ?', [$operatorId]);
    }
}
