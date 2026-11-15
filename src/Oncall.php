<?php
declare(strict_types=1);

namespace Bhelpdesk;

/**
 * On-call rota ("reperibilità").
 *
 * Every week (Monday to Sunday) has one on-call operator; a single day can be
 * given to another operator, which overrides the week for that day only.
 * Every operator can see the calendar, administrators can change it.
 */
final class Oncall
{
    public static function canEdit(array $op): bool
    {
        return ($op['role'] ?? '') === 'admin';
    }

    /** Operators that can be on call (active, not billing) */
    public static function operators(): array
    {
        return Db::all("SELECT id, name FROM operators WHERE active = 1 AND role <> 'billing' ORDER BY name");
    }

    /** Monday of the week containing $date (Y-m-d) */
    public static function monday(string $date): string
    {
        $d = new \DateTimeImmutable($date);
        return $d->modify('-' . ((int) $d->format('N') - 1) . ' days')->format('Y-m-d');
    }

    /** Valid "Y-m-d" date or null */
    public static function date(mixed $v): ?string
    {
        $v = (string) $v;
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
        return $d && $d->format('Y-m-d') === $v ? $v : null;
    }

    /** Valid operator id for the rota, 0 = remove */
    private static function operatorId(mixed $id): ?int
    {
        $id = (int) $id;
        if ($id === 0) {
            return 0;
        }
        foreach (self::operators() as $o) {
            if ((int) $o['id'] === $id) {
                return $id;
            }
        }
        return null;
    }

    /** Sets (or removes with 0) the on-call operator of a week; false if the operator is not valid */
    public static function setWeek(string $monday, mixed $operatorId, array $by): bool
    {
        $id = self::operatorId($operatorId);
        if ($id === null) {
            return false;
        }
        $monday = self::monday($monday);
        if ($id === 0) {
            Db::exec('DELETE FROM oncall_weeks WHERE week_start = ?', [$monday]);
        } else {
            Db::exec('INSERT INTO oncall_weeks (week_start, operator_id, updated_by) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE operator_id = VALUES(operator_id), updated_by = VALUES(updated_by)',
                [$monday, $id, (int) $by['id']]);
        }
        return true;
    }

    /** Sets (or removes with 0, back to the week's operator) the override of a single day */
    public static function setDay(string $day, mixed $operatorId, array $by): bool
    {
        $id = self::operatorId($operatorId);
        if ($id === null) {
            return false;
        }
        if ($id === 0) {
            Db::exec('DELETE FROM oncall_days WHERE day = ?', [$day]);
        } else {
            Db::exec('INSERT INTO oncall_days (day, operator_id, updated_by) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE operator_id = VALUES(operator_id), updated_by = VALUES(updated_by)',
                [$day, $id, (int) $by['id']]);
        }
        return true;
    }

    /** On-call operator of a day (single-day override first, then the week): ['id', 'name'] or null */
    public static function forDay(string $day): ?array
    {
        $r = Db::one('SELECT o.id, o.name FROM oncall_days d JOIN operators o ON o.id = d.operator_id WHERE d.day = ?', [$day])
            ?? Db::one('SELECT o.id, o.name FROM oncall_weeks w JOIN operators o ON o.id = w.operator_id WHERE w.week_start = ?', [self::monday($day)]);
        return $r ? ['id' => (int) $r['id'], 'name' => $r['name']] : null;
    }

    /**
     * Calendar of a month, Monday-first weeks:
     * [ ['monday' => Y-m-d, 'number' => ISO week, 'oncall' => [id,name]|null,
     *    'days' => [ ['date', 'day', 'in_month', 'today', 'oncall' => [id,name,override]|null], x7 ] ], ... ]
     */
    public static function grid(int $year, int $month): array
    {
        $first = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $start = new \DateTimeImmutable(self::monday($first->format('Y-m-d')));
        $last = $first->modify('last day of this month');
        $end = $last->modify('+' . (7 - (int) $last->format('N')) . ' days');

        $weeks = [];
        foreach (Db::all('SELECT w.week_start, o.id, o.name FROM oncall_weeks w JOIN operators o ON o.id = w.operator_id
                          WHERE w.week_start BETWEEN ? AND ?', [$start->format('Y-m-d'), $end->format('Y-m-d')]) as $r) {
            $weeks[$r['week_start']] = ['id' => (int) $r['id'], 'name' => $r['name']];
        }
        $days = [];
        foreach (Db::all('SELECT d.day, o.id, o.name FROM oncall_days d JOIN operators o ON o.id = d.operator_id
                          WHERE d.day BETWEEN ? AND ?', [$start->format('Y-m-d'), $end->format('Y-m-d')]) as $r) {
            $days[$r['day']] = ['id' => (int) $r['id'], 'name' => $r['name']];
        }

        $today = date('Y-m-d');
        $grid = [];
        for ($w = $start; $w <= $end; $w = $w->modify('+7 days')) {
            $monday = $w->format('Y-m-d');
            $row = ['monday' => $monday, 'number' => (int) $w->format('W'), 'oncall' => $weeks[$monday] ?? null, 'days' => []];
            for ($i = 0; $i < 7; $i++) {
                $d = $w->modify("+$i days");
                $date = $d->format('Y-m-d');
                $oncall = isset($days[$date]) ? $days[$date] + ['override' => true]
                    : ($row['oncall'] ? $row['oncall'] + ['override' => false] : null);
                $row['days'][] = [
                    'date'     => $date,
                    'day'      => (int) $d->format('j'),
                    'in_month' => (int) $d->format('n') === $month,
                    'today'    => $date === $today,
                    'oncall'   => $oncall,
                ];
            }
            $grid[] = $row;
        }
        return $grid;
    }
}
