<?php
declare(strict_types=1);

define('BHD_ROOT', dirname(__DIR__));

$configFile = BHD_ROOT . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Configurazione mancante: copia config/config.example.php in config/config.php');
}
$GLOBALS['bhd_config'] = require $configFile;

spl_autoload_register(static function (string $class): void {
    $prefix = 'Bhelpdesk\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = BHD_ROOT . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require __DIR__ . '/helpers.php';

date_default_timezone_set((string) config('timezone', 'Europe/Rome'));

if (PHP_SAPI !== 'cli') {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'");
}
