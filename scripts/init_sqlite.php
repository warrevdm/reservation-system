<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit;
}

$root = dirname(__DIR__);
$configPath = $root . '/config/config.php';
$dbPath = $root . '/database/dev.sqlite';

if (!is_file($configPath)) {
    $config = <<<'PHP_CONFIG'
<?php
return [
    'app' => [
        'name' => 'De Pasto Reservaties',
        'base_url' => '',
        'timezone' => 'Europe/Brussels',
        'session_name' => 'pasto_reservation_admin',
        'debug' => true,
    ],
    'database' => [
        'dsn' => 'sqlite:' . dirname(__DIR__) . '/database/dev.sqlite',
        'user' => null,
        'password' => null,
        'options' => [],
    ],
    'mail' => ['enabled' => false, 'from_email' => 'reservaties@de-pasto.be', 'from_name' => 'De Pasto', 'notify_email' => 'reservaties@de-pasto.be'],
    'security' => [
        'trust_proxy' => false,
        'public_rate_limit' => 12,
        'public_rate_window_minutes' => 15,
        'form_min_seconds' => 3,
        'form_max_seconds' => 7200,
        'recaptcha' => [
            'enabled' => false,
            'site_key' => '',
            'secret_key' => '',
            'min_score' => 0.5,
            'action' => 'reservation',
            'expected_hostname' => '',
        ],
    ],
];
PHP_CONFIG;
    file_put_contents($configPath, $config . PHP_EOL);
    echo "config/config.php aangemaakt voor SQLite.\n";
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(file_get_contents($root . '/database/schema.sqlite.sql'));
echo "SQLite database klaar: database/dev.sqlite\n";
echo "Maak nu een admin: php scripts/create_admin.php admin@de-pasto.be 'minstens-10-tekens' 'Warre'\n";
