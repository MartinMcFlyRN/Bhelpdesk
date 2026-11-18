<?php
declare(strict_types=1);

use Bhelpdesk\IpGuard;
use Bhelpdesk\Lang;

function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['bhd_config'] ?? [];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

/** Translate a key (see lang/en.php). Returns plain text: escape it with e() in HTML. */
function __(string $key, array $params = []): string
{
    return Lang::get($key, $params);
}

/** Translate and HTML-escape in one go (shortcut for templates). */
function t(string $key, array $params = []): string
{
    return e(Lang::get($key, $params));
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

function client_ip(): string
{
    return IpGuard::clientIp((array) config('trusted_proxies', []));
}

/** Flash messages hold a translation key, translated when displayed. */
function flash(string $type, string $key, array $params = []): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'key' => $key, 'params' => $params];
}

function take_flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

/** Previously submitted field value (to refill forms) */
function old(string $key, string $default = ''): string
{
    return (string) ($_POST[$key] ?? $default);
}

function fmt_date(?string $dt): string
{
    if (!$dt) {
        return '—';
    }
    return date(__('date_format'), strtotime($dt));
}

/** Plain text -> safe HTML with line breaks */
function nl2br_e(string $text): string
{
    return nl2br(e($text), false);
}

/** Up to two initials for the avatar, e.g. "Mario Rossi" -> "MR" */
function initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
    $first = mb_substr($parts[0], 0, 1);
    $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
    return mb_strtoupper($first . $last);
}

function status_label(string $s): string
{
    return __('status.' . $s);
}

function priority_label(string $p): string
{
    return __('priority.' . $p);
}

/**
 * System messages are stored as JSON events so they can be shown
 * in the language of whoever is reading them.
 */
/**
 * Footer of the pages ('customer' or 'operators') set in /op/config/general,
 * with {year} and {app_name}; '' when empty or disabled for that area.
 */
function site_footer(string $area): string
{
    $html = (string) config('footer.html', '');
    if ($html === '' || !config('footer.' . $area, true)) {
        return '';
    }
    return \Bhelpdesk\Html::sanitize(strtr($html, ['{year}' => date('Y'), '{app_name}' => e((string) config('app_name', 'Bhelpdesk'))]));
}

function system_message(string $body): string
{
    $ev = json_decode($body, true);
    if (!is_array($ev) || !isset($ev['event'])) {
        return $body; // free text (legacy)
    }
    switch ($ev['event']) {
        case 'reopened':
            return __('log.reopened');
        case 'closed_by_customer':
            return __('log.closed_by_customer', ['name' => (string) ($ev['name'] ?? '?')]);
        case 'task_added':
            return __('log.task_added', ['who' => (string) ($ev['by'] ?? '?'), 'task' => (string) ($ev['task'] ?? ''), 'to' => (string) ($ev['to'] ?? ''), 'due' => (string) ($ev['due'] ?? '')]);
        case 'invoice_added':
            return __('log.invoice_added', [
                'who' => (string) ($ev['by'] ?? '?'), 'id' => (string) ($ev['id'] ?? '?'),
                'amount' => \Bhelpdesk\Invoices::money((float) ($ev['amount'] ?? 0)),
                'type' => __('inv.rec_' . (in_array($ev['recurrence'] ?? '', \Bhelpdesk\Invoices::RECURRENCES, true) ? $ev['recurrence'] : 'once')),
            ]);
        case 'quote_added':
            return __('log.quote_added', ['who' => (string) ($ev['by'] ?? '?'), 'id' => (string) ($ev['id'] ?? '?'), 'amount' => \Bhelpdesk\Invoices::money((float) ($ev['amount'] ?? 0))]);
        case 'task_done':
            return __('log.task_done', ['who' => (string) ($ev['by'] ?? '?'), 'task' => (string) ($ev['task'] ?? '')]);
        case 'merged':
            return __('log.merged', ['who' => (string) ($ev['by'] ?? '?'), 'tickets' => implode(', ', (array) ($ev['tickets'] ?? []))]);
        case 'email_files_skipped':
            return __('log.email_files_skipped', ['files' => implode(', ', (array) ($ev['files'] ?? []))]);
        case 'opened_by_operator':
            return __('log.opened_by_operator', ['who' => (string) ($ev['by'] ?? '?')]);
        case 'customer_added':
            return __('log.customer_added', ['who' => (string) ($ev['by'] ?? '?'), 'name' => (string) ($ev['name'] ?? '?')]);
        case 'customer_removed':
            return __('log.customer_removed', ['who' => (string) ($ev['by'] ?? '?'), 'name' => (string) ($ev['name'] ?? '?')]);
        case 'customer_changed':
            $params = ['who' => (string) ($ev['by'] ?? '?'), 'from' => (string) ($ev['from'] ?? '?'), 'to' => (string) ($ev['to'] ?? '?')];
            return !empty($ev['kept']) ? __('log.customer_changed_kept', $params) : __('log.customer_changed', $params);
        case 'changed':
            $parts = [];
            foreach ((array) ($ev['changes'] ?? []) as $c) {
                $parts[] = match ($c[0] ?? '') {
                    'status'     => __('log.status', ['from' => status_label((string) $c[1]), 'to' => status_label((string) $c[2])]),
                    'priority'   => __('log.priority', ['from' => priority_label((string) $c[1]), 'to' => priority_label((string) $c[2])]),
                    'department' => __('log.department', ['name' => $c[1] ?? __('log.none')]),
                    'assigned'   => __('log.assigned', ['name' => $c[1] ?? __('log.none')]),
                    default      => '',
                };
            }
            return __('log.changed', ['who' => (string) ($ev['by'] ?? '?'), 'changes' => implode(__('log.separator'), array_filter($parts))]);
        default:
            return $body;
    }
}

/**
 * Compact page list for a pager: first, last, current and its neighbours,
 * null where pages are skipped. 1 … 6 7 [8] 9 10 … 140
 * @return list<int|null>
 */
function pager_pages(int $page, int $pages, int $around = 2): array
{
    $out = [];
    $last = 0;
    for ($i = 1; $i <= $pages; $i++) {
        if ($i === 1 || $i === $pages || abs($i - $page) <= $around) {
            if ($last && $i - $last > 1) {
                $out[] = $i - $last === 2 ? $last + 1 : null; // a single hidden page is shown instead of "…"
            }
            $out[] = $i;
            $last = $i;
        }
    }
    return $out;
}
