<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$configFile = $root . '/config/config.php';
if (!is_file($configFile)) {
    $configFile = $root . '/config/config.example.php';
}

$GLOBALS['pasto_config'] = require $configFile;

date_default_timezone_set((string) ($GLOBALS['pasto_config']['app']['timezone'] ?? 'Europe/Brussels'));

session_name((string) ($GLOBALS['pasto_config']['app']['session_name'] ?? 'pasto_reservation_admin'));
if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once $root . '/app/Database.php';
require_once $root . '/app/helpers.php';
require_once $root . '/app/BotProtection.php';
require_once $root . '/app/Auth.php';
require_once $root . '/app/ReservationService.php';
require_once $root . '/app/Mailer.php';

try {
    $GLOBALS['pasto_db'] = new Database($GLOBALS['pasto_config']['database']);
} catch (Throwable $e) {
    if ((bool) ($GLOBALS['pasto_config']['app']['debug'] ?? false)) {
        throw $e;
    }
    http_response_code(500);
    echo 'De reservatiemodule kan momenteel geen verbinding maken met de database.';
    exit;
}
