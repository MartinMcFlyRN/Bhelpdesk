<?php
// Copia questo file in config/config.php e personalizzalo.
// config/config.php NON deve finire nel repository.

return [
    'app_name' => 'Bhelpdesk',
    'base_url' => 'https://helpdesk.example.it',   // senza slash finale
    'timezone' => 'Europe/Rome',

    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'name'     => 'bhelpdesk',
        'user'     => 'bhelpdesk',
        'password' => 'CAMBIAMI',
        // 'socket' => '/run/mysqld/mysqld.sock',  // se valorizzato ha priorità su host/port
    ],

    // Cookie solo su HTTPS: lascia true in produzione
    'cookie_secure' => true,

    // Area operatori: IP/CIDR ammessi (IPv4 e IPv6).
    // È un secondo livello di difesa: il filtro principale sta in nginx.
    // Array vuoto = nessun filtro applicativo (resta solo nginx).
    'op_allowed_ips' => [
        '127.0.0.1',
        '::1',
        // '203.0.113.10',
        // '10.0.0.0/8',
    ],

    // Se l'app sta dietro un reverse proxy / CDN, elenca qui gli IP del proxy:
    // solo da questi verrà letto X-Forwarded-For per ricavare l'IP reale.
    'trusted_proxies' => [],

    // Scadenza sessione operatore per inattività (secondi)
    'op_session_idle' => 1800,

    // Limiti anti-abuso: [numero massimo, finestra in secondi]
    'throttle' => [
        'op_login_fail'  => [5, 900],
        'ticket_create'  => [5, 3600],
        'status_lookup'  => [10, 900],
    ],

    // Il cliente può rispondere a un ticket chiuso (lo riapre)
    'allow_reopen' => true,
];
