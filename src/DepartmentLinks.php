<?php
declare(strict_types=1);

namespace Bhelpdesk;

/**
 * Useful links of a department (name + URL), edited in the department
 * configuration and listed in the operators' "Links" menu.
 */
final class DepartmentLinks
{
    public const MAX = 50;

    public static function forDepartment(int $departmentId): array
    {
        return Db::all('SELECT id, name, url FROM department_links WHERE department_id = ? ORDER BY sort_order, id', [$departmentId]);
    }

    /**
     * Links an operator sees: those of the departments they work on (all the
     * active departments for operators who see every department).
     * @return array<string, list<array{name: string, url: string}>> department name => links
     */
    public static function forOperator(array $op): array
    {
        if (($op['role'] ?? '') === 'billing') {
            return [];
        }
        $sql = 'SELECT d.name AS department, l.name, l.url FROM department_links l JOIN departments d ON d.id = l.department_id WHERE d.active = 1';
        $params = [];
        if (!Operators::seesAll($op)) {
            $ids = Operators::departmentIds((int) $op['id']);
            if (!$ids) {
                return [];
            }
            $sql .= ' AND d.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $params = $ids;
        }
        $out = [];
        foreach (Db::all($sql . ' ORDER BY d.sort_order, d.name, l.sort_order, l.id', $params) as $r) {
            $out[$r['department']][] = ['name' => $r['name'], 'url' => $r['url']];
        }
        return $out;
    }

    /**
     * Replaces the links of a department with the rows of the form
     * (rows with neither name nor URL are ignored). Nothing is saved on errors.
     * @param list<array{name?: string, url?: string}> $rows
     * @return list<string> errors
     */
    public static function save(int $departmentId, array $rows): array
    {
        $clean = [];
        $errors = [];
        foreach ($rows as $i => $r) {
            $name = trim((string) ($r['name'] ?? ''));
            $url = trim((string) ($r['url'] ?? ''));
            if ($name === '' && $url === '') {
                continue;
            }
            $n = count($clean) + count($errors) + 1;
            if ($name === '' || mb_strlen($name) > 100) {
                $errors[] = __('dlinks.err_name', ['n' => $n]);
                continue;
            }
            if ($url !== '' && !preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
                $url = 'https://' . $url; // "intranet.example.com" -> https://...
            }
            if (!self::validUrl($url)) {
                $errors[] = __('dlinks.err_url', ['name' => $name]);
                continue;
            }
            $clean[] = [$name, $url];
        }
        if (count($clean) > self::MAX) {
            $errors[] = __('dlinks.err_max', ['max' => self::MAX]);
        }
        if ($errors) {
            return $errors;
        }
        Db::exec('DELETE FROM department_links WHERE department_id = ?', [$departmentId]);
        foreach ($clean as $pos => [$name, $url]) {
            Db::exec('INSERT INTO department_links (department_id, name, url, sort_order) VALUES (?, ?, ?, ?)', [$departmentId, $name, $url, $pos]);
        }
        return [];
    }

    /** http(s) URLs only (no javascript: and the like) */
    public static function validUrl(string $url): bool
    {
        return strlen($url) <= 500 && filter_var($url, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#i', $url);
    }
}
