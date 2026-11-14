<?php
declare(strict_types=1);

namespace Bhelpdesk;

/**
 * Canned responses (managed in /op/config/canned) and the placeholders shared
 * with department signatures.
 */
final class Canned
{
    /** Placeholders available in canned responses and signatures */
    public const PLACEHOLDERS = [
        '{customer_name}', '{customer_email}', '{ticket_number}', '{ticket_subject}',
        '{department_name}', '{operator_name}', '{operator_email}', '{app_name}',
    ];

    public static function all(): array
    {
        return Db::all(
            'SELECT c.*, d.name AS department_name
               FROM canned_responses c LEFT JOIN departments d ON d.id = c.department_id
              ORDER BY c.active DESC, c.title'
        );
    }

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM canned_responses WHERE id = ?', [$id]);
    }

    /** Active responses usable on a ticket: generic ones + those of its department */
    public static function forTicket(array $ticket): array
    {
        return Db::all(
            'SELECT c.id, c.title, c.body, d.name AS department_name
               FROM canned_responses c LEFT JOIN departments d ON d.id = c.department_id
              WHERE c.active = 1 AND (c.department_id IS NULL OR c.department_id = ?)
              ORDER BY c.department_id IS NULL, c.title',
            [(int) ($ticket['department_id'] ?? 0)]
        );
    }

    /**
     * @return array{values: array, errors: list<string>}
     */
    public static function validate(array $in, ?int $id = null): array
    {
        $v = [
            'title'         => trim((string) ($in['title'] ?? '')),
            'body'          => trim((string) ($in['body'] ?? '')),
            'department_id' => (int) ($in['department_id'] ?? 0) ?: null,
            'active'        => !empty($in['active']) ? 1 : 0,
        ];
        $errors = [];
        if ($v['title'] === '' || mb_strlen($v['title']) > 150) {
            $errors[] = __('val.canned_title');
        } elseif (Db::value('SELECT 1 FROM canned_responses WHERE title = ? AND id <> ?', [$v['title'], (int) $id])) {
            $errors[] = __('val.canned_duplicate');
        }
        if ($v['body'] === '' || mb_strlen($v['body']) > 20000) {
            $errors[] = __('val.canned_body');
        }
        if ($v['department_id'] && !Db::value('SELECT 1 FROM departments WHERE id = ?', [$v['department_id']])) {
            $v['department_id'] = null;
        }
        return ['values' => $v, 'errors' => $errors];
    }

    public static function create(array $v, int $operatorId): int
    {
        return Db::insert(
            'INSERT INTO canned_responses (title, body, department_id, active, updated_by) VALUES (?, ?, ?, ?, ?)',
            [$v['title'], $v['body'], $v['department_id'], $v['active'], $operatorId]
        );
    }

    public static function update(int $id, array $v, int $operatorId): void
    {
        Db::exec(
            'UPDATE canned_responses SET title = ?, body = ?, department_id = ?, active = ?, updated_by = ? WHERE id = ?',
            [$v['title'], $v['body'], $v['department_id'], $v['active'], $operatorId, $id]
        );
    }

    public static function delete(int $id): void
    {
        Db::exec('DELETE FROM canned_responses WHERE id = ?', [$id]);
    }

    /** Values of the placeholders for a ticket and the replying operator */
    public static function context(array $ticket, array $op): array
    {
        return [
            '{customer_name}'   => (string) ($ticket['customer_name'] ?? ''),
            '{customer_email}'  => (string) ($ticket['customer_email'] ?? ''),
            '{ticket_number}'   => (string) ($ticket['number'] ?? ''),
            '{ticket_subject}'  => (string) ($ticket['subject'] ?? ''),
            '{department_name}' => (string) ($ticket['department_name'] ?? ''),
            '{operator_name}'   => (string) ($op['name'] ?? ''),
            '{operator_email}'  => (string) ($op['email'] ?? ''),
            '{app_name}'        => (string) config('app_name', 'Bhelpdesk'),
        ];
    }

    public static function render(string $text, array $context): string
    {
        return strtr($text, $context);
    }

    /**
     * Signature of the ticket's department with placeholders filled in,
     * or '' when the department has none. Appended to reply emails.
     */
    public static function signature(array $ticket, array $op): string
    {
        if (empty($ticket['department_id'])) {
            return '';
        }
        $sig = (string) Db::value('SELECT signature FROM departments WHERE id = ?', [(int) $ticket['department_id']]);
        return trim($sig) === '' ? '' : self::render(trim($sig), self::context($ticket, $op));
    }
}
