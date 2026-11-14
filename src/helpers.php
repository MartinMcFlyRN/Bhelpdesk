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
function system_message(string $body): string
{
    $ev = json_decode($body, true);
    if (!is_array($ev) || !isset($ev['event'])) {
        return $body; // free text (legacy)
    }
    switch ($ev['event']) {
        case 'reopened':
            return __('log.reopened');
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
