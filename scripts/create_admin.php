<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/bootstrap.php';

$email = trim((string) ($argv[1] ?? ''));
$password = (string) ($argv[2] ?? '');
$name = trim((string) ($argv[3] ?? 'De Pasto Admin'));

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
    fwrite(STDERR, "Gebruik: php scripts/create_admin.php admin@de-pasto.be 'sterk-wachtwoord' 'Naam'\nWachtwoord: minimaal 10 tekens.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$existing = db()->fetch('SELECT id FROM users WHERE LOWER(email) = LOWER(?)', [$email]);
if ($existing) {
    db()->execute('UPDATE users SET name = ?, password_hash = ?, is_active = 1 WHERE id = ?', [$name, $hash, (int) $existing['id']]);
    echo "Admin bijgewerkt: {$email}\n";
} else {
    db()->execute('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)', [$name, $email, $hash, 'admin']);
    echo "Admin aangemaakt: {$email}\n";
}
