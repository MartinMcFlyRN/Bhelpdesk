<?php
declare(strict_types=1);

// Remove the two-factor authentication of an operator (lost phone and no
// recovery codes, e.g. the only administrator). At the next login the
// operator sets it up again with a new QR code.
// Usage: sudo -u bhelpdesk php8.3 bin/reset-2fa.php <username>

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__) . '/src/bootstrap.php';

use Bhelpdesk\{Db, TwoFactor};

$username = (string) ($argv[1] ?? '');
$op = $username !== '' ? Db::one('SELECT id, name FROM operators WHERE username = ?', [$username]) : null;
if (!$op) {
    fwrite(STDERR, "Usage: php bin/reset-2fa.php <username>\nOperators: " . implode(', ', Db::column('SELECT username FROM operators ORDER BY username')) . "\n");
    exit(1);
}
TwoFactor::reset((int) $op['id']);
echo "2FA removed for {$op['name']} ({$username}): it will be set up again at the next login.\n";
