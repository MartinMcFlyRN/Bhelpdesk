<?php
declare(strict_types=1);

namespace Bhelpdesk;

final class Departments
{
    /** All departments with the number of tickets using them */
    public static function all(): array
    {
        return Db::all(
            'SELECT d.*, (SELECT COUNT(*) FROM tickets t WHERE t.department_id = d.id) AS ticket_count
               FROM departments d
              ORDER BY d.sort_order, d.name'
        );
    }

    public static function find(int $id): ?array
    {
        return Db::one(
            'SELECT d.*, (SELECT COUNT(*) FROM tickets t WHERE t.department_id = d.id) AS ticket_count FROM departments d WHERE d.id = ?',
            [$id]
        );
    }

    /**
     * @return array{values: array, errors: list<string>}
     */
    public static function validate(array $in, ?int $id = null): array
    {
        $v = [
            'name'       => trim((string) ($in['name'] ?? '')),
            'sort_order' => (int) ($in['sort_order'] ?? 0),
            'active'     => !empty($in['active']) ? 1 : 0,
            'send_identity_id' => (int) ($in['send_identity_id'] ?? 0) ?: null,
            'signature'  => ($in['format'] ?? '') === 'html' ? Html::sanitize((string) ($in['signature'] ?? '')) : Html::fromText((string) ($in['signature'] ?? '')),
        ];
        $errors = [];
        if ($v['name'] === '' || mb_strlen($v['name']) > 100) {
            $errors[] = __('val.dep_name');
        } elseif (Db::value('SELECT 1 FROM departments WHERE name = ? AND id <> ?', [$v['name'], (int) $id])) {
            $errors[] = __('val.dep_duplicate');
        }
        if (Html::isEmpty($v['signature'])) {
            $v['signature'] = '';
        }
        if (mb_strlen($v['signature']) > 20000) {
            $errors[] = __('val.dep_signature');
        }
        if ($v['send_identity_id'] && !Db::value('SELECT 1 FROM mail_identities WHERE id = ?', [$v['send_identity_id']])) {
            $v['send_identity_id'] = null;
        }
        $v['sort_order'] = max(-999, min(999, $v['sort_order']));
        return ['values' => $v, 'errors' => $errors];
    }

    public static function create(array $v): int
    {
        return Db::insert('INSERT INTO departments (name, sort_order, active, signature) VALUES (?, ?, ?, ?)', [$v['name'], $v['sort_order'], $v['active'], $v['signature'] ?: null]);
    }

    public static function update(int $id, array $v): void
    {
        Db::exec('UPDATE departments SET name = ?, sort_order = ?, active = ?, signature = ?, send_identity_id = ? WHERE id = ?', [$v['name'], $v['sort_order'], $v['active'], $v['signature'] ?: null, $v['send_identity_id'], $id]);
    }

    /** Only departments never used by a ticket can be deleted */
    public static function delete(int $id): bool
    {
        if (Db::value('SELECT 1 FROM tickets WHERE department_id = ? LIMIT 1', [$id])
            || Db::value('SELECT 1 FROM mail_identities WHERE department_id = ? LIMIT 1', [$id])) {
            return false;
        }
        Db::exec('DELETE FROM departments WHERE id = ?', [$id]);
        return true;
    }
}
