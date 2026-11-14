<?php
declare(strict_types=1);

// Create an operator from the command line (needed for the first administrator).
// Usage: php bin/create-operator.php [--role=admin|agent]
// Output language: BHD_LANG=it php bin/create-operator.php

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__) . '/src/bootstrap.php';

use Bhelpdesk\Db;

$opts = getopt('', ['role::']);
$role = ($opts['role'] ?? 'admin') === 'agent' ? 'agent' : 'admin';

$ask = static function (string $label, bool $hidden = false): string {
    echo $label . ': ';
    if ($hidden && DIRECTORY_SEPARATOR === '/') {
        @system('stty -echo 2>/dev/null');
        $v = trim((string) fgets(STDIN));
        @system('stty echo 2>/dev/null');
        echo PHP_EOL;
        return $v;
    }
    return trim((string) fgets(STDIN));
};

$username = $ask(__('cli.username'));
$name     = $ask(__('cli.name'));
$email    = strtolower($ask(__('cli.email')));
$password = $ask(__('cli.password'), true);
$confirm  = $ask(__('cli.confirm'), true);

$err = [];
if (!preg_match('/^[a-z0-9._-]{3,50}$/i', $username)) $err[] = __('cli.err_username');
if ($name === '') $err[] = __('cli.err_name');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err[] = __('cli.err_email');
if (strlen($password) < 12) $err[] = __('cli.err_password');
if ($password !== $confirm) $err[] = __('cli.err_mismatch');
if ($err) {
    fwrite(STDERR, __('cli.error', ['errors' => implode(', ', $err)]) . PHP_EOL);
    exit(1);
}

try {
    $id = Db::insert(
        'INSERT INTO operators (username, name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)',
        [$username, $name, $email, password_hash($password, PASSWORD_DEFAULT), $role]
    );
} catch (PDOException $ex) {
    fwrite(STDERR, __('cli.db_error', ['error' => $ex->getMessage()]) . PHP_EOL);
    exit(1);
}
echo __('cli.created', ['id' => $id, 'role' => $role]) . PHP_EOL;
