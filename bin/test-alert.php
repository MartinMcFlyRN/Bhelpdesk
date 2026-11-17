<?php
declare(strict_types=1);

/**
 * Sends the "assigned to you" alert of a ticket to an operator right now and
 * prints what happened (the same row is written in the email log).
 * Useful when an operator says the assignment emails do not arrive.
 *
 *   php bin/test-alert.php --ticket=<ticket number> --operator=<username>
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require __DIR__ . '/../src/bootstrap.php';

use Bhelpdesk\{Alerts, Db};

$opt = getopt('', ['ticket:', 'operator:']);
$ticket = Db::one('SELECT id, number FROM tickets WHERE number = ?', [(string) ($opt['ticket'] ?? '')]);
$op = Db::one('SELECT id, name, email, active, alert_assign FROM operators WHERE username = ?', [(string) ($opt['operator'] ?? '')]);
if (!$ticket || !$op) {
    fwrite(STDERR, "Usage: php bin/test-alert.php --ticket=<ticket number> --operator=<username>\n");
    fwrite(STDERR, !$ticket ? "Ticket not found.\n" : "Operator not found.\n");
    exit(1);
}
echo "Email alerts to operators: " . (config('mail.alerts', true) ? 'on' : 'OFF (Configuration -> General)') . "\n";
echo "Operator {$op['name']} <{$op['email']}>, active: " . ($op['active'] ? 'yes' : 'NO') . ", 'assigned to me' alert: " . ($op['alert_assign'] ? 'on' : 'OFF (profile)') . "\n";

$last = (int) Db::value('SELECT COALESCE(MAX(id), 0) FROM mail_messages');
Alerts::assigned((int) $ticket['id'], (int) $op['id'], ['id' => 0, 'name' => 'bin/test-alert.php']);
Alerts::flush();

$row = Db::one('SELECT status, address, subject, note FROM mail_messages WHERE id > ? AND ticket_id = ? ORDER BY id DESC LIMIT 1', [$last, (int) $ticket['id']]);
if (!$row) {
    echo "Nothing sent and nothing logged.\n";
    exit(1);
}
echo "Result: {$row['status']} -> {$row['address']}\n  {$row['subject']}\n" . ($row['note'] ? "  {$row['note']}\n" : '');
exit($row['status'] === 'alert' ? 0 : 1);
