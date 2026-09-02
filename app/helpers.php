<?php

declare(strict_types=1);

function config(string $path, mixed $default = null): mixed
{
    $config = $GLOBALS['pasto_config'] ?? [];
    foreach (explode('.', $path) as $segment) {
        if (!is_array($config) || !array_key_exists($segment, $config)) {
            return $default;
        }
        $config = $config[$segment];
    }
    return $config;
}

function db(): Database
{
    return $GLOBALS['pasto_db'];
}

function base_url(string $path = ''): string
{
    $base = rtrim((string) config('app.base_url', ''), '/');
    $path = '/' . ltrim($path, '/');
    return $base === '' ? $path : $base . $path;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function request_json(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $_POST;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    }
    return (string) $_SESSION['csrf_token'];
}

function verify_csrf(?string $token = null): void
{
    $token ??= $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? null);
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        json_response(['ok' => false, 'message' => 'Ongeldige sessie. Vernieuw de pagina en probeer opnieuw.'], 419);
    }
}

function client_ip(): string
{
    $trustProxy = (bool) config('security.trust_proxy', false);
    if ($trustProxy && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function setting(string $key, mixed $default = null): mixed
{
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $row = db()->fetch('SELECT setting_value FROM settings WHERE setting_key = ?', [$key]);
    if (!$row) {
        return $cache[$key] = $default;
    }

    $value = $row['setting_value'];
    $decoded = json_decode((string) $value, true);
    return $cache[$key] = (json_last_error() === JSON_ERROR_NONE ? $decoded : $value);
}

function is_valid_date(string $date): bool
{
    $d = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    return $d !== false && $d->format('Y-m-d') === $date;
}

function reservation_code(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 8; $i++) {
        $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $code;
}
