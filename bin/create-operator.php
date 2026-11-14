<?php
declare(strict_types=1);

// Crea un operatore da riga di comando (serve per il primo amministratore).
// Uso: php bin/create-operator.php [--role=admin|agent]

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
        system('stty -echo');
        $v = trim((string) fgets(STDIN));
        system('stty echo');
        echo PHP_EOL;
        return $v;
    }
    return trim((string) fgets(STDIN));
};

$username = $ask('Username');
$name     = $ask('Nome completo');
$email    = strtolower($ask('Email'));
$password = $ask('Password (min 12 caratteri)', true);
$confirm  = $ask('Ripeti password', true);

$err = [];
if (!preg_match('/^[a-z0-9._-]{3,50}$/i', $username)) $err[] = 'username non valido';
if ($name === '') $err[] = 'nome obbligatorio';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err[] = 'email non valida';
if (strlen($password) < 12) $err[] = 'password troppo corta';
if ($password !== $confirm) $err[] = 'le password non coincidono';
if ($err) {
    fwrite(STDERR, 'Errore: ' . implode(', ', $err) . PHP_EOL);
    exit(1);
}

try {
    $id = Db::insert(
        'INSERT INTO operators (username, name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)',
        [$username, $name, $email, password_hash($password, PASSWORD_DEFAULT), $role]
    );
} catch (PDOException $ex) {
    fwrite(STDERR, 'Errore DB: ' . $ex->getMessage() . PHP_EOL);
    exit(1);
}
echo "Operatore #$id ($role) creato." . PHP_EOL;
