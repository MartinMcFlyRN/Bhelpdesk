<?php
declare(strict_types=1);

use Bhelpdesk\IpGuard;

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

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

/** Valore di un campo inviato in precedenza (per ripopolare i form) */
function old(string $key, string $default = ''): string
{
    return (string) ($_POST[$key] ?? $default);
}

function fmt_date(?string $dt): string
{
    if (!$dt) {
        return '—';
    }
    return date('d/m/Y H:i', strtotime($dt));
}

/** Testo semplice -> HTML sicuro con a capo */
function nl2br_e(string $text): string
{
    return nl2br(e($text), false);
}

const STATUS_LABELS = [
    'open'     => 'Aperto',
    'answered' => 'Risposto',
    'on_hold'  => 'In sospeso',
    'closed'   => 'Chiuso',
];

const PRIORITY_LABELS = [
    'low'    => 'Bassa',
    'normal' => 'Normale',
    'high'   => 'Alta',
    'urgent' => 'Urgente',
];

function status_label(string $s): string
{
    return STATUS_LABELS[$s] ?? $s;
}

function priority_label(string $p): string
{
    return PRIORITY_LABELS[$p] ?? $p;
}
