<?php
declare(strict_types=1);

// Translation checker.
//   php bin/check-translations.php        -> report for every language
//   php bin/check-translations.php de     -> report for lang/de.php only
//
// Reports:
//   - keys used in the code but missing from lang/en.php (bugs)
//   - keys in lang/en.php not used anywhere (probably obsolete)
//   - for each other language: missing keys (shown in English) and extra keys
// Exit code 1 if lang/en.php is missing keys used in the code.

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
$base = require $root . '/lang/en.php';

// keys referenced in the code: __('x'), t('x'), flash('type', 'x')
$used = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $p = $f->getPathname();
    if ($f->getExtension() !== 'php' || str_contains($p, '/lang/') || str_contains($p, '/vendor/') || $p === __FILE__) {
        continue;
    }
    $src = (string) file_get_contents($p);
    preg_match_all("/(?:\\b__|\\bt)\\(\\s*'([a-z0-9_.]*[a-z0-9_])'/", $src, $m);
    preg_match_all("/flash\\(\\s*'[a-z]+'\\s*,\\s*(?:\\\$\\w+\\s*\\?\\s*)?'([a-z0-9_.]+)'(?:\\s*:\\s*'([a-z0-9_.]+)')?/", $src, $m2);
    foreach (array_merge($m[1], $m2[1], array_filter($m2[2])) as $k) {
        if (str_ends_with($k, '_')) {
            continue; // prefix of a key built at runtime, e.g. t('mi.sec_' . $s)
        }
        $used[$k][] = substr($p, strlen($root) + 1);
    }
    // keys picked in expressions, e.g. __($first ? 'a.b' : 'a.c'): any string literal equal to a key
    preg_match_all("/'([a-z0-9_]+\\.[a-z0-9_.]*[a-z0-9_])'/", $src, $m3);
    foreach (array_intersect($m3[1], array_keys($base)) as $k) {
        $used[$k][] = substr($p, strlen($root) + 1);
    }
}
// keys built dynamically in the code
foreach (['status.open', 'status.answered', 'status.on_hold', 'status.closed',
          'priority.low', 'priority.normal', 'priority.high', 'priority.urgent',
          'role.admin', 'role.agent', 'date_format', 'lang.name'] as $k) {
    $used[$k][] = '(dynamic)';
}

// keys built from a prefix at runtime (settings labels/help, config menu)
foreach (array_keys($base) as $k) {
    if (str_starts_with($k, 'set.') || str_starts_with($k, 'cfg.menu.') || str_starts_with($k, 'ph.') || str_starts_with($k, 'mi.sec_') || str_starts_with($k, 'mi.keep_')) {
        $used[$k][] = '(dynamic)';
    }
}

$fail = false;
$missingBase = array_diff_key($used, $base);
if ($missingBase) {
    $fail = true;
    echo "Keys used in the code but missing from lang/en.php:\n";
    foreach ($missingBase as $k => $files) {
        echo "  - $k  (" . implode(', ', array_unique($files)) . ")\n";
    }
}
$unused = array_diff_key($base, $used);
if ($unused) {
    echo "Keys in lang/en.php not used in the code:\n  - " . implode("\n  - ", array_keys($unused)) . "\n";
}

$only = $argv[1] ?? null;
foreach (glob($root . '/lang/*.php') as $file) {
    $code = basename($file, '.php');
    if ($code === 'en' || ($only && $only !== $code)) {
        continue;
    }
    $tr = require $file;
    $missing = array_diff_key($base, $tr);
    $extra = array_diff_key($tr, $base);
    $same = array_keys(array_filter($tr, static fn ($v, $k) => isset($base[$k]) && $base[$k] === $v && !in_array($k, ['login.username', 'login.password', 'ops.username', 'new.email', 'ops.email', 'cli.username', 'cli.email', 'log.separator', 'ticket.title'], true), ARRAY_FILTER_USE_BOTH));
    $pct = $base ? round(100 * (count($base) - count($missing)) / count($base)) : 100;
    echo "\n[$code] {$pct}% (" . (count($base) - count($missing)) . '/' . count($base) . ")\n";
    if ($missing) echo "  missing:    " . implode(', ', array_keys($missing)) . "\n";
    if ($extra)   echo "  not in en:  " . implode(', ', array_keys($extra)) . "\n";
    if ($same)    echo "  identical to English (untranslated?): " . implode(', ', $same) . "\n";
    // placeholders must match
    foreach ($tr as $k => $v) {
        if (!isset($base[$k])) continue;
        preg_match_all('/:[a-z_]+/', $base[$k], $a);
        preg_match_all('/:[a-z_]+/', (string) $v, $b);
        sort($a[0]); sort($b[0]);
        if ($a[0] !== $b[0]) {
            echo "  placeholder mismatch in $k: en=" . implode(' ', $a[0]) . " $code=" . implode(' ', $b[0]) . "\n";
        }
    }
}

exit($fail ? 1 : 0);
