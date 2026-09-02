<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit;
require_once dirname(__DIR__) . '/app/bootstrap.php';

$date = (new DateTimeImmutable())->format('Y-m-d');
$demo = [
    ['17:00', 2, 'Lotte Peeters', 'lotte@example.com', '0470 11 22 33', 'Graag rustig tafeltje.'],
    ['18:00', 4, 'Familie Janssens', 'familie@example.com', '0471 22 33 44', '1 kinderstoel graag.'],
    ['18:30', 6, 'Thomas & vrienden', 'thomas@example.com', '0472 33 44 55', 'Verjaardag.'],
    ['19:30', 2, 'Sofie Vermeiren', 'sofie@example.com', '0473 44 55 66', ''],
    ['20:00', 5, 'Jeroen De Smet', 'jeroen@example.com', '0474 55 66 77', 'Allergie noten bij 1 persoon.'],
];

foreach ($demo as $i => [$time, $party, $name, $email, $phone, $notes]) {
    $code = 'DEMO' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);
    if (db()->fetch('SELECT id FROM reservations WHERE public_code = ?', [$code])) continue;
    db()->execute(
        'INSERT INTO reservations (public_code, reservation_date, start_time, duration_minutes, party_size, guest_name, guest_email, guest_phone, notes, status, source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$code, $date, $time . ':00', 120, $party, $name, $email, $phone, $notes, $i < 2 ? 'new' : 'confirmed', 'manual']
    );
}

echo "Demo-reservaties toegevoegd voor {$date}.\n";
