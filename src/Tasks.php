<?php
declare(strict_types=1);

namespace Bhelpdesk;

/**
 * Tasks for operators.
 *
 * A task becomes "to do" at due_at and stays in the box under the menu of
 * its assignees until someone marks it done:
 *  - target 'all'        -> every operator
 *  - target 'department' -> the operators of that department
 *  - target 'operator'   -> only that operator
 * A done task is done for everybody. Every operator can see every task in the
 * list; the creator and the administrators can edit or delete it.
 */
final class Tasks
{
    private const SELECT = '
        SELECT k.*, d.name AS department_name, o.name AS operator_name,
               c.name AS created_by_name, x.name AS done_by_name,
               t.number AS ticket_number, t.subject AS ticket_subject
          FROM tasks k
          LEFT JOIN tickets t     ON t.id = k.ticket_id
          LEFT JOIN departments d ON d.id = k.department_id
          LEFT JOIN operators o   ON o.id = k.operator_id
          LEFT JOIN operators c   ON c.id = k.created_by
          LEFT JOIN operators x   ON x.id = k.done_by';

    public static function find(int $id): ?array
    {
        return Db::one(self::SELECT . ' WHERE k.id = ?', [$id]);
    }

    /** SQL condition: tasks assigned to this operator (directly, to their departments or to all) */
    private static function mineCondition(array $op): array
    {
        $cond = "k.target = 'all' OR (k.target = 'operator' AND k.operator_id = ?)";
        $params = [(int) $op['id']];
        $deps = Operators::departmentIds((int) $op['id']);
        if ($deps) {
            $cond .= " OR (k.target = 'department' AND k.department_id IN (" . implode(',', array_fill(0, count($deps), '?')) . '))';
            $params = array_merge($params, $deps);
        }
        return ['(' . $cond . ')', $params];
    }

    /** Tasks to do now for this operator (shown in the box under the menu) */
    public static function activeFor(array $op): array
    {
        [$mine, $params] = self::mineCondition($op);
        return Db::all(self::SELECT . " WHERE k.done_at IS NULL AND k.due_at <= NOW() AND $mine ORDER BY k.due_at, k.id LIMIT 50", $params);
    }

    public static function isAssignedTo(array $task, array $op): bool
    {
        return match ($task['target']) {
            'all'        => true,
            'operator'   => (int) $task['operator_id'] === (int) $op['id'],
            'department' => in_array((int) $task['department_id'], Operators::departmentIds((int) $op['id']), true),
            default      => false,
        };
    }

    /** Assignees and administrators may mark a task done / reopen it */
    public static function canComplete(array $task, array $op): bool
    {
        return ($op['role'] ?? '') === 'admin' || self::isAssignedTo($task, $op);
    }

    public static function canEdit(array $task, array $op): bool
    {
        return ($op['role'] ?? '') === 'admin' || (int) $task['created_by'] === (int) $op['id'];
    }

    /** 'done', 'scheduled' (not yet active), 'today' (active since today) or 'late' (active since before today) */
    public static function state(array $task): string
    {
        if ($task['done_at'] !== null) {
            return 'done';
        }
        $due = strtotime((string) $task['due_at']);
        if ($due > time()) {
            return 'scheduled';
        }
        return $due >= strtotime('today') ? 'today' : 'late';
    }

    /**
     * List with filters: state ('open' = scheduled + to do, 'todo', 'scheduled', 'done', 'all'),
     * mine (only tasks assigned to $op), q (title).
     * @return array{rows: list<array>, total: int}
     */
    public static function search(array $f, array $op, int $page = 1, int $perPage = 30): array
    {
        $where = ['1 = 1'];
        $params = [];
        switch ($f['state'] ?? 'open') {
            case 'todo':
                $where[] = 'k.done_at IS NULL AND k.due_at <= NOW()';
                break;
            case 'scheduled':
                $where[] = 'k.done_at IS NULL AND k.due_at > NOW()';
                break;
            case 'done':
                $where[] = 'k.done_at IS NOT NULL';
                break;
            case 'all':
                break;
            default:
                $where[] = 'k.done_at IS NULL';
        }
        if (!empty($f['mine'])) {
            [$mine, $mp] = self::mineCondition($op);
            $where[] = $mine;
            $params = array_merge($params, $mp);
        }
        if (($f['q'] ?? '') !== '') {
            $where[] = 'k.title LIKE ?';
            $params[] = '%' . addcslashes((string) $f['q'], '%_\\') . '%';
        }
        $sqlWhere = implode(' AND ', $where);
        $total = (int) Db::value('SELECT COUNT(*) FROM tasks k WHERE ' . $sqlWhere, $params);
        // to do first (oldest first), then scheduled (soonest first), done last (most recent first)
        $order = ($f['state'] ?? 'open') === 'done'
            ? 'k.done_at DESC'
            : 'k.done_at IS NOT NULL, (k.due_at > NOW()), k.due_at, k.id';
        $rows = Db::all(self::SELECT . ' WHERE ' . $sqlWhere . ' ORDER BY ' . $order . ' LIMIT ' . $perPage . ' OFFSET ' . (max(0, $page - 1) * $perPage), $params);
        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array{values: array, errors: list<string>} */
    public static function validate(array $in): array
    {
        $v = [
            'title'       => trim((string) preg_replace('/\s+/u', ' ', (string) ($in['title'] ?? ''))),
            'description' => Html::sanitize((string) ($in['description'] ?? '')),
            'due_date'    => trim((string) ($in['due_date'] ?? '')),
            'due_time'    => trim((string) ($in['due_time'] ?? '')),
            'assign'      => trim((string) ($in['assign'] ?? 'all')),
        ];
        if (Html::isEmpty($v['description'])) {
            $v['description'] = '';
        }
        $errors = [];
        if ($v['title'] === '' || mb_strlen($v['title']) > 200) {
            $errors[] = __('tasks.err_title');
        }
        $d = \DateTime::createFromFormat('!Y-m-d', $v['due_date']);
        if (!$d || $d->format('Y-m-d') !== $v['due_date']) {
            $errors[] = __('tasks.err_date');
        }
        if ($v['due_time'] !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v['due_time'])) {
            $errors[] = __('tasks.err_time');
        }
        $v['target'] = 'all';
        $v['department_id'] = null;
        $v['operator_id'] = null;
        if (preg_match('/^d:(\d+)$/', $v['assign'], $m) && Db::value('SELECT 1 FROM departments WHERE id = ?', [(int) $m[1]])) {
            $v['target'] = 'department';
            $v['department_id'] = (int) $m[1];
        } elseif (preg_match('/^o:(\d+)$/', $v['assign'], $m) && Db::value("SELECT 1 FROM operators WHERE id = ? AND active = 1 AND role <> 'billing'", [(int) $m[1]])) {
            $v['target'] = 'operator';
            $v['operator_id'] = (int) $m[1];
        } elseif ($v['assign'] !== 'all') {
            $errors[] = __('tasks.err_assign');
        }
        return ['values' => $v, 'errors' => $errors];
    }

    private static function dueAt(array $v): string
    {
        return $v['due_date'] . ' ' . ($v['due_time'] !== '' ? $v['due_time'] : '00:00') . ':00';
    }

    /** $ticket: the ticket the task is created from (its history records it) */
    public static function create(array $v, array $op, ?array $ticket = null): int
    {
        $id = Db::insert(
            'INSERT INTO tasks (title, description, due_at, has_time, target, department_id, operator_id, ticket_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$v['title'], $v['description'] !== '' ? $v['description'] : null, self::dueAt($v), (int) ($v['due_time'] !== ''), $v['target'], $v['department_id'], $v['operator_id'], $ticket ? (int) $ticket['id'] : null, (int) $op['id']]
        );
        if ($ticket) {
            $task = self::find($id);
            Tickets::addMessage((int) $ticket['id'], 'system', (int) $op['id'], json_encode([
                'event' => 'task_added', 'by' => $op['name'], 'task' => $v['title'],
                'to' => self::assigneeLabel($task), 'due' => self::dueLabel($task),
            ], JSON_UNESCAPED_UNICODE), true, null);
        }
        return $id;
    }

    /** Tasks linked to a ticket (sidebar of the ticket) */
    public static function forTicket(int $ticketId): array
    {
        return Db::all(self::SELECT . ' WHERE k.ticket_id = ? ORDER BY k.done_at IS NOT NULL, k.due_at, k.id', [$ticketId]);
    }

    public static function update(int $id, array $v): void
    {
        Db::exec(
            'UPDATE tasks SET title = ?, description = ?, due_at = ?, has_time = ?, target = ?, department_id = ?, operator_id = ? WHERE id = ?',
            [$v['title'], $v['description'] !== '' ? $v['description'] : null, self::dueAt($v), (int) ($v['due_time'] !== ''), $v['target'], $v['department_id'], $v['operator_id'], $id]
        );
    }

    /** Form values of an existing task */
    public static function formValues(array $t): array
    {
        return [
            'title'       => (string) $t['title'],
            'description' => (string) ($t['description'] ?? ''),
            'due_date'    => substr((string) $t['due_at'], 0, 10),
            'due_time'    => $t['has_time'] ? substr((string) $t['due_at'], 11, 5) : '',
            'assign'      => match ($t['target']) {
                'department' => 'd:' . (int) $t['department_id'],
                'operator'   => 'o:' . (int) $t['operator_id'],
                default      => 'all',
            },
        ];
    }

    public static function markDone(array $task, array $op): void
    {
        $n = Db::exec('UPDATE tasks SET done_at = NOW(), done_by = ? WHERE id = ? AND done_at IS NULL', [(int) $op['id'], (int) $task['id']]);
        if ($n && $task['ticket_id'] !== null) {
            Tickets::addMessage((int) $task['ticket_id'], 'system', (int) $op['id'], json_encode(['event' => 'task_done', 'by' => $op['name'], 'task' => $task['title']], JSON_UNESCAPED_UNICODE), true, null);
        }
    }

    public static function reopen(int $id): void
    {
        Db::exec('UPDATE tasks SET done_at = NULL, done_by = NULL WHERE id = ?', [$id]);
    }

    public static function delete(int $id): void
    {
        Db::exec('DELETE FROM tasks WHERE id = ?', [$id]);
    }

    /** "Everyone" / department name / operator name */
    public static function assigneeLabel(array $t): string
    {
        return match ($t['target']) {
            'department' => __('tasks.dep_prefix') . ' ' . (string) $t['department_name'],
            'operator'   => (string) $t['operator_name'],
            default      => __('tasks.everyone'),
        };
    }

    /** Due date for display: only the day when no time was set */
    public static function dueLabel(array $t): string
    {
        $ts = strtotime((string) $t['due_at']);
        $fmt = (string) __('date_format');
        $dateOnly = trim((string) preg_replace('/[ ,]*[HhGgis:]+.*$/', '', $fmt));
        return $t['has_time'] ? date($fmt, $ts) : date($dateOnly !== '' ? $dateOnly : 'd/m/Y', $ts);
    }
}
