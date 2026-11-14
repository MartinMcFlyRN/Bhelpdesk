<?php
declare(strict_types=1);

namespace Bhelpdesk;

/**
 * Direct access links to a ticket, one per (ticket, customer) issue.
 *
 * - Only the SHA-256 hash of the token is stored: a database leak does not
 *   reveal working links. The plain link is shown/sent once, when issued.
 * - A link expires `customer_link_days` days (default 30) after the last
 *   message visible to the customer (customer or operator reply, not internal
 *   notes or system events). A new reply extends every link of the ticket.
 * - Access is also re-checked against the ticket customers: a customer removed
 *   from the ticket cannot use their link any more.
 */
final class AccessLinks
{
    public static function days(): int
    {
        return max(1, (int) config('customer_link_days', 30));
    }

    /** Create a new link and return its full URL (the only time the token is known) */
    public static function issue(int $ticketId, int $customerId): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        Db::exec(
            'INSERT INTO ticket_links (token_hash, ticket_id, customer_id) VALUES (?, ?, ?)',
            [hash('sha256', $token), $ticketId, $customerId]
        );
        return self::url($token);
    }

    public static function url(string $token): string
    {
        return rtrim((string) config('base_url'), '/') . '/customer/t/' . $token;
    }

    /** Time of the last message visible to customers (falls back to ticket creation) */
    public static function lastActivity(int $ticketId): string
    {
        return (string) (Db::value(
            "SELECT MAX(created_at) FROM ticket_messages WHERE ticket_id = ? AND is_internal = 0 AND author_type IN ('customer', 'operator')",
            [$ticketId]
        ) ?? Db::value('SELECT created_at FROM tickets WHERE id = ?', [$ticketId]));
    }

    public static function expiresAt(int $ticketId): int
    {
        return strtotime(self::lastActivity($ticketId)) + self::days() * 86400;
    }

    /**
     * Resolve a token.
     * @return array{status: 'ok'|'expired'|'invalid', link?: array, ticket?: array}
     */
    public static function check(string $token): array
    {
        if ($token === '' || strlen($token) > 128) {
            return ['status' => 'invalid'];
        }
        $link = Db::one('SELECT * FROM ticket_links WHERE token_hash = ?', [hash('sha256', $token)]);
        if (!$link) {
            return ['status' => 'invalid'];
        }
        $ticket = Tickets::find((int) $link['ticket_id']);
        if (!$ticket || !Tickets::customerHasAccess($ticket, (int) $link['customer_id'])) {
            return ['status' => 'invalid'];
        }
        if (time() > self::expiresAt((int) $ticket['id'])) {
            return ['status' => 'expired', 'link' => $link, 'ticket' => $ticket];
        }
        return ['status' => 'ok', 'link' => $link, 'ticket' => $ticket];
    }

    /** Is the link with this hash still valid? (re-checked on every request of a link session) */
    public static function stillValid(string $tokenHash): bool
    {
        $link = Db::one('SELECT * FROM ticket_links WHERE token_hash = ?', [$tokenHash]);
        if (!$link) {
            return false;
        }
        $ticket = Tickets::find((int) $link['ticket_id']);
        return $ticket
            && Tickets::customerHasAccess($ticket, (int) $link['customer_id'])
            && time() <= self::expiresAt((int) $ticket['id']);
    }

    public static function touch(string $tokenHash): void
    {
        Db::exec('UPDATE ticket_links SET last_used_at = NOW() WHERE token_hash = ?', [$tokenHash]);
    }

    /** Remove the links of a customer on a ticket (e.g. when removed from it) */
    public static function revoke(int $ticketId, int $customerId): void
    {
        Db::exec('DELETE FROM ticket_links WHERE ticket_id = ? AND customer_id = ?', [$ticketId, $customerId]);
    }
}
