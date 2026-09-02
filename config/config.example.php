<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'De Pasto Reservaties',
        'base_url' => '', // e.g. https://reservaties.de-pasto.be (no trailing slash)
        'timezone' => 'Europe/Brussels',
        'session_name' => 'pasto_reservation_admin',
        'debug' => false,
    ],
    'database' => [
        // Production example:
        // 'dsn' => 'mysql:host=localhost;dbname=pasto_reservations;charset=utf8mb4',
        // Local development example:
        // 'dsn' => 'sqlite:' . dirname(__DIR__) . '/database/dev.sqlite',
        'dsn' => 'mysql:host=localhost;dbname=pasto_reservations;charset=utf8mb4',
        'user' => 'CHANGE_ME',
        'password' => 'CHANGE_ME',
        'options' => [],
    ],
    'mail' => [
        'enabled' => false,
        'from_email' => 'reservaties@de-pasto.be',
        'from_name' => 'De Pasto',
        'notify_email' => 'reservaties@de-pasto.be',
    ],
    'security' => [
        'trust_proxy' => false,
        'public_rate_limit' => 12,
        'public_rate_window_minutes' => 15,
        'form_min_seconds' => 3,
        'form_max_seconds' => 7200,
        'recaptcha' => [
            'enabled' => false,
            'mode' => 'v2', // v2 = zichtbare "Ik ben geen robot" checkbox; v3 = onzichtbare score
            'site_key' => '',
            'secret_key' => '',
            'min_score' => 0.5, // alleen voor v3
            'action' => 'reservation', // alleen voor v3
            'expected_hostname' => '', // productie: reserveer.de-pasto.be; lokaal leeg laten
        ],
    ],
];
