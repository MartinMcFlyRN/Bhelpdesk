<?php
// Copy this file to config/config.php and adjust it.
// config/config.php must NOT be committed to the repository.

return [
    'app_name' => 'Bhelpdesk',
    'base_url' => 'https://helpdesk.example.com',   // no trailing slash
    'timezone' => 'Europe/Rome',

    // ---- Languages ----
    // Default language when the browser preference does not match any file in lang/.
    'default_language'  => 'en',
    // Languages offered in the selector. Empty = every file found in lang/.
    // English is always available as the base language.
    'enabled_languages' => [],   // e.g. ['it', 'de']

    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'name'     => 'bhelpdesk',
        'user'     => 'bhelpdesk',
        'password' => 'CHANGE_ME',
        // 'socket' => '/run/mysqld/mysqld.sock',  // takes precedence over host/port
    ],

    // Cookies over HTTPS only: keep true in production
    // (set false only for plain-HTTP tests, otherwise forms show "Session expired").
    'cookie_secure' => true,

    // Operators area: allowed IPs/CIDRs (IPv4 and IPv6).
    // Second line of defence: the main filter is in nginx/Apache.
    // Empty array = no application-level filter.
    'op_allowed_ips' => [
        '127.0.0.1',
        '::1',
        // '203.0.113.10',
        // '10.0.0.0/8',
    ],

    // If the app sits behind a reverse proxy / CDN, list the proxy IPs here:
    // X-Forwarded-For is read only for requests coming from them.
    'trusted_proxies' => [],

    // Operator session idle timeout (seconds)
    'op_session_idle' => 1800,

    // Anti-abuse limits: [max attempts, window in seconds]
    'throttle' => [
        'op_login_fail'  => [5, 900],
        'ticket_create'  => [5, 3600],
        'status_lookup'  => [10, 900],
    ],

    // Customers may reply to a closed ticket (it gets reopened)
    'allow_reopen' => true,
];
