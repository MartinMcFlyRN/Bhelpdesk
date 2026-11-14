<?php
declare(strict_types=1);

// Download the POP3 mailboxes of the email identities and open/update tickets.
// Run it from cron every minute as the PHP-FPM user, e.g. /etc/cron.d/bhelpdesk:
//   * * * * * bhelpdesk php8.3 /var/www/bhelpdesk/bin/fetch-mail.php --quiet
//
// Usage:
//   php bin/fetch-mail.php                     all identities with POP3 enabled
//   php bin/fetch-mail.php --identity=3        only one identity
//   php bin/fetch-mail.php --file=msg.eml --identity=3
//                                              process a saved message (test), no POP3
//   --quiet                                    print only errors

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__) . '/src/bootstrap.php';

use Bhelpdesk\{Db, MailFetcher};

$opts = getopt('', ['identity::', 'file::', 'quiet']);
$quiet = isset($opts['quiet']);
$identityId = isset($opts['identity']) ? (int) $opts['identity'] : null;

if (isset($opts['file'])) {
    $file = (string) $opts['file'];
    $identity = $identityId ? Db::one('SELECT * FROM mail_identities WHERE id = ?', [$identityId]) : null;
    $raw = is_file($file) && is_readable($file) ? file_get_contents($file) : false;
    if ($raw === false) {
        fwrite(STDERR, "Cannot read the file '$file' (current directory: " . getcwd() . ").\n"
            . "Save the message as .eml (\"Download message\" / \"Show original\" in the mail client) and give its path.\n");
    }
    if (!$identity) {
        fwrite(STDERR, ($identityId ? "Identity $identityId does not exist." : 'Missing --identity=ID.') . " Identities:\n");
        foreach (Db::all('SELECT id, email, pop_enabled FROM mail_identities ORDER BY id') as $i) {
            fwrite(STDERR, sprintf("  --identity=%d  %s%s\n", $i['id'], $i['email'], $i['pop_enabled'] ? '' : ' (POP3 off)'));
        }
    }
    if ($raw === false || !$identity) {
        exit(1);
    }
    $r = MailFetcher::process($identity, $raw);
    echo $r['status'] . ($r['ticket_id'] ? ' ticket #' . Db::value('SELECT number FROM tickets WHERE id = ?', [$r['ticket_id']]) : '') . ($r['note'] !== '' ? ' - ' . $r['note'] : '') . "\n";
    exit(0);
}

$lock = MailFetcher::lock();
if (!$lock) {
    $quiet || print("Another download is in progress.\n");
    exit(0);
}
$failed = false;
foreach (MailFetcher::run($identityId) as $r) {
    if (!$r['ok']) {
        $failed = true;
        fwrite(STDERR, $r['identity'] . ': ' . $r['message'] . "\n");
    } elseif (!$quiet) {
        echo $r['identity'] . ': ' . $r['message'] . "\n";
    }
}
exit($failed ? 2 : 0);
