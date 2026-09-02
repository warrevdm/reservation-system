<?php

declare(strict_types=1);

final class ReservationService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? db();
    }

    public function openingHours(string $date): ?array
    {
        if (!is_valid_date($date)) {
            return null;
        }

        $hours = setting('opening_hours', [
            '1' => ['open' => '16:00', 'close' => '00:00'],
            '2' => ['open' => '16:00', 'close' => '00:00'],
            '3' => ['open' => '16:00', 'close' => '00:00'],
            '4' => ['open' => '16:00', 'close' => '00:00'],
            '5' => ['open' => '16:00', 'close' => '01:00'],
            '6' => ['open' => '10:00', 'close' => '02:00'],
            '7' => ['open' => '10:00', 'close' => '22:00'],
        ]);

        $day = (new DateTimeImmutable($date))->format('N');
        $row = is_array($hours) ? ($hours[$day] ?? null) : null;
        if (!is_array($row) || empty($row['open']) || empty($row['close'])) {
            return null;
        }

        return ['open' => (string) $row['open'], 'close' => (string) $row['close']];
    }

    public function availableSlots(string $date, int $partySize): array
    {
        $hours = $this->openingHours($date);
        if (!$hours) {
            return [];
        }

        $interval = max(15, (int) setting('booking_interval_minutes', 30));
        $duration = max($interval, (int) setting('default_duration_minutes', 120));
        $leadMinutes = max(0, (int) setting('min_lead_minutes', 60));
        $daysAhead = max(1, (int) setting('bookable_days_ahead', 90));
        $maxParty = max(1, (int) setting('max_online_party_size', 12));
        if ($partySize < 1 || $partySize > $maxParty) {
            return [];
        }

        $today = new DateTimeImmutable('today');
        $requestedDay = new DateTimeImmutable($date);
        if ($requestedDay < $today || $requestedDay > $today->modify('+' . $daysAhead . ' days')) {
            return [];
        }

        [$open, $close] = $this->serviceWindow($date, $hours['open'], $hours['close']);
        $latestStart = $close->modify('-' . $duration . ' minutes');
        if ($latestStart < $open) {
            return [];
        }

        $reservations = $this->reservationsForDate($date);
        $maxCovers = $this->maxCovers();
        $nowWithLead = (new DateTimeImmutable())->modify('+' . $leadMinutes . ' minutes');
        $slots = [];

        for ($cursor = $open; $cursor <= $latestStart; $cursor = $cursor->modify('+' . $interval . ' minutes')) {
            if ($cursor < $nowWithLead) {
                continue;
            }

            $end = $cursor->modify('+' . $duration . ' minutes');
            $covers = 0;
            foreach ($reservations as $reservation) {
                if (in_array($reservation['status'], ['cancelled', 'no_show'], true)) {
                    continue;
                }
                [$resStart, $resEnd] = $this->reservationWindow($reservation);
                if ($this->overlaps($cursor, $end, $resStart, $resEnd)) {
                    $covers += (int) $reservation['party_size'];
                }
            }

            $available = ($covers + $partySize) <= $maxCovers;
            $slots[] = [
                'time' => $cursor->format('H:i'),
                'available' => $available,
                'remaining_covers' => max(0, $maxCovers - $covers),
            ];
        }

        return $slots;
    }

    public function create(array $input): array
    {
        $date = trim((string) ($input['date'] ?? ''));
        $time = trim((string) ($input['time'] ?? ''));
        $partySize = (int) ($input['party_size'] ?? 0);
        $name = trim((string) ($input['name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $phone = trim((string) ($input['phone'] ?? ''));
        $notes = trim((string) ($input['notes'] ?? ''));

        if (!is_valid_date($date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
            throw new InvalidArgumentException('Kies een geldige datum en tijd.');
        }
        if ($partySize < 1 || $partySize > (int) setting('max_online_party_size', 12)) {
            throw new InvalidArgumentException('Kies een geldig aantal personen.');
        }
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            throw new InvalidArgumentException('Vul je naam in.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Vul een geldig e-mailadres in.');
        }
        if (mb_strlen($phone) < 6 || mb_strlen($phone) > 30) {
            throw new InvalidArgumentException('Vul een geldig telefoonnummer in.');
        }
        if (mb_strlen($notes) > 1000) {
            throw new InvalidArgumentException('Je opmerking is te lang.');
        }

        $slots = $this->availableSlots($date, $partySize);
        $slot = null;
        foreach ($slots as $candidate) {
            if ($candidate['time'] === $time) {
                $slot = $candidate;
                break;
            }
        }
        if (!$slot || !$slot['available']) {
            throw new RuntimeException('Dit tijdstip is intussen niet meer beschikbaar. Kies een ander tijdstip.');
        }

        $duration = max(30, (int) setting('default_duration_minutes', 120));
        $code = reservation_code();
        $this->db->execute(
            'INSERT INTO reservations (public_code, reservation_date, start_time, duration_minutes, party_size, guest_name, guest_email, guest_phone, notes, status, source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$code, $date, $time . ':00', $duration, $partySize, $name, $email, $phone, $notes, 'new', 'website']
        );

        $id = $this->db->lastInsertId();
        $reservation = $this->reservationById($id);
        if (!$reservation) {
            throw new RuntimeException('Reservatie kon niet worden opgeslagen.');
        }

        return $reservation;
    }

    public function reservationById(int $id): ?array
    {
        $reservation = $this->db->fetch('SELECT * FROM reservations WHERE id = ?', [$id]);
        if (!$reservation) {
            return null;
        }
        $reservation['tables'] = $this->db->fetchAll(
            'SELECT dt.id, dt.name, dt.seats, dt.room_id FROM reservation_tables rt JOIN dining_tables dt ON dt.id = rt.table_id WHERE rt.reservation_id = ? ORDER BY dt.name',
            [$id]
        );
        return $reservation;
    }

    public function dayData(string $date): array
    {
        if (!is_valid_date($date)) {
            throw new InvalidArgumentException('Ongeldige datum.');
        }

        $rooms = $this->db->fetchAll('SELECT * FROM rooms WHERE is_active = 1 ORDER BY sort_order, id');
        $tables = $this->db->fetchAll('SELECT * FROM dining_tables WHERE is_active = 1 ORDER BY room_id, name');
        $reservations = $this->reservationsForDate($date);

        foreach ($reservations as &$reservation) {
            $reservation['tables'] = $this->db->fetchAll(
                'SELECT dt.id, dt.name, dt.seats, dt.room_id FROM reservation_tables rt JOIN dining_tables dt ON dt.id = rt.table_id WHERE rt.reservation_id = ? ORDER BY dt.name',
                [(int) $reservation['id']]
            );
        }
        unset($reservation);

        return [
            'rooms' => $rooms,
            'tables' => $tables,
            'reservations' => $reservations,
            'opening_hours' => $this->openingHours($date),
            'summary' => $this->summary($reservations),
        ];
    }

    public function assign(int $reservationId, int $tableId): array
    {
        $reservation = $this->reservationById($reservationId);
        $table = $this->db->fetch('SELECT * FROM dining_tables WHERE id = ? AND is_active = 1', [$tableId]);
        if (!$reservation || !$table) {
            throw new InvalidArgumentException('Reservatie of tafel niet gevonden.');
        }

        $conflict = $this->assignmentConflict($reservation, $tableId);
        if ($conflict) {
            throw new RuntimeException('Deze tafel is op dat moment al bezet door ' . $conflict['guest_name'] . ' om ' . substr((string) $conflict['start_time'], 0, 5) . '.');
        }

        $this->db->pdo()->beginTransaction();
        try {
            $this->db->execute('DELETE FROM reservation_tables WHERE reservation_id = ?', [$reservationId]);
            $this->db->execute('INSERT INTO reservation_tables (reservation_id, table_id) VALUES (?, ?)', [$reservationId, $tableId]);
            if ($reservation['status'] === 'new') {
                $this->db->execute("UPDATE reservations SET status = 'confirmed', updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$reservationId]);
            } else {
                $this->db->execute('UPDATE reservations SET updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$reservationId]);
            }
            $this->audit('reservation_assigned', 'reservation', $reservationId, ['table_id' => $tableId]);
            $this->db->pdo()->commit();
        } catch (Throwable $e) {
            $this->db->pdo()->rollBack();
            throw $e;
        }

        return [
            'reservation' => $this->reservationById($reservationId),
            'capacity_warning' => (int) $reservation['party_size'] > (int) $table['seats'],
            'table' => $table,
        ];
    }

    public function unassign(int $reservationId): void
    {
        $this->db->execute('DELETE FROM reservation_tables WHERE reservation_id = ?', [$reservationId]);
        $this->audit('reservation_unassigned', 'reservation', $reservationId, []);
    }

    public function updateStatus(int $reservationId, string $status): void
    {
        $allowed = ['new', 'confirmed', 'seated', 'completed', 'cancelled', 'no_show'];
        if (!in_array($status, $allowed, true)) {
            throw new InvalidArgumentException('Ongeldige status.');
        }
        $this->db->execute('UPDATE reservations SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$status, $reservationId]);
        $this->audit('reservation_status', 'reservation', $reservationId, ['status' => $status]);
    }

    public function saveReservation(array $input): array
    {
        $id = (int) ($input['id'] ?? 0);
        $date = trim((string) ($input['date'] ?? ''));
        $time = trim((string) ($input['time'] ?? ''));
        $partySize = max(1, (int) ($input['party_size'] ?? 1));
        $name = trim((string) ($input['name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $phone = trim((string) ($input['phone'] ?? ''));
        $notes = trim((string) ($input['notes'] ?? ''));

        if (!is_valid_date($date) || !preg_match('/^\d{2}:\d{2}$/', $time) || $name === '') {
            throw new InvalidArgumentException('Datum, tijd en naam zijn verplicht.');
        }

        $duration = max(30, (int) ($input['duration_minutes'] ?? setting('default_duration_minutes', 120)));
        if ($id > 0) {
            $this->db->execute(
                'UPDATE reservations SET reservation_date = ?, start_time = ?, duration_minutes = ?, party_size = ?, guest_name = ?, guest_email = ?, guest_phone = ?, notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                [$date, $time . ':00', $duration, $partySize, $name, $email, $phone, $notes, $id]
            );
            $this->audit('reservation_updated', 'reservation', $id, []);
        } else {
            $code = reservation_code();
            $this->db->execute(
                'INSERT INTO reservations (public_code, reservation_date, start_time, duration_minutes, party_size, guest_name, guest_email, guest_phone, notes, status, source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$code, $date, $time . ':00', $duration, $partySize, $name, $email, $phone, $notes, 'confirmed', 'manual']
            );
            $id = $this->db->lastInsertId();
            $this->audit('reservation_created_manual', 'reservation', $id, []);
        }

        return $this->reservationById($id) ?? [];
    }

    public function saveTable(array $input): array
    {
        $id = (int) ($input['id'] ?? 0);
        $roomId = (int) ($input['room_id'] ?? 0);
        $name = trim((string) ($input['name'] ?? ''));
        $seats = max(1, min(30, (int) ($input['seats'] ?? 2)));
        $shape = in_array(($input['shape'] ?? 'round'), ['round', 'square', 'rectangle'], true) ? (string) $input['shape'] : 'round';

        if ($roomId < 1 || $name === '') {
            throw new InvalidArgumentException('Zone en tafelnaam zijn verplicht.');
        }

        if ($id > 0) {
            $this->db->execute('UPDATE dining_tables SET room_id = ?, name = ?, seats = ?, shape = ? WHERE id = ?', [$roomId, $name, $seats, $shape, $id]);
        } else {
            $count = $this->db->fetch('SELECT COUNT(*) AS c FROM dining_tables WHERE room_id = ?', [$roomId]);
            $offset = ((int) ($count['c'] ?? 0)) * 7;
            $x = 12 + ($offset % 70);
            $y = 18 + (($offset * 2) % 60);
            $this->db->execute('INSERT INTO dining_tables (room_id, name, seats, shape, pos_x, pos_y, width_pct, height_pct) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [$roomId, $name, $seats, $shape, $x, $y, 14, 14]);
            $id = $this->db->lastInsertId();
        }

        return $this->db->fetch('SELECT * FROM dining_tables WHERE id = ?', [$id]) ?? [];
    }

    public function saveTablePosition(int $tableId, float $x, float $y): void
    {
        $x = max(0.0, min(92.0, $x));
        $y = max(0.0, min(88.0, $y));
        $this->db->execute('UPDATE dining_tables SET pos_x = ?, pos_y = ? WHERE id = ?', [$x, $y, $tableId]);
    }

    public function saveSettings(array $input): void
    {
        $keys = ['booking_interval_minutes', 'default_duration_minutes', 'max_online_party_size', 'bookable_days_ahead', 'min_lead_minutes', 'max_covers_per_slot', 'opening_hours'];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            $value = $input[$key];
            $encoded = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;
            $existing = $this->db->fetch('SELECT setting_key FROM settings WHERE setting_key = ?', [$key]);
            if ($existing) {
                $this->db->execute('UPDATE settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?', [$encoded, $key]);
            } else {
                $this->db->execute('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)', [$key, $encoded]);
            }
        }
    }

    public function publicRateLimit(string $fingerprint): bool
    {
        $limit = max(1, (int) config('security.public_rate_limit', 12));
        $windowMinutes = max(1, (int) config('security.public_rate_window_minutes', 15));
        $cutoff = (new DateTimeImmutable())->modify('-' . $windowMinutes . ' minutes')->format('Y-m-d H:i:s');
        $this->db->execute('DELETE FROM request_throttle WHERE created_at < ?', [$cutoff]);
        $row = $this->db->fetch('SELECT COUNT(*) AS c FROM request_throttle WHERE fingerprint = ?', [$fingerprint]);
        if ((int) ($row['c'] ?? 0) >= $limit) {
            return false;
        }
        $this->db->execute('INSERT INTO request_throttle (fingerprint) VALUES (?)', [$fingerprint]);
        return true;
    }

    private function reservationsForDate(string $date): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM reservations WHERE reservation_date = ? ORDER BY start_time, created_at",
            [$date]
        );
    }

    private function maxCovers(): int
    {
        $explicit = (int) setting('max_covers_per_slot', 0);
        if ($explicit > 0) {
            return $explicit;
        }
        $row = $this->db->fetch('SELECT COALESCE(SUM(seats), 0) AS seats FROM dining_tables WHERE is_active = 1');
        return max(1, (int) ($row['seats'] ?? 1));
    }

    private function serviceWindow(string $date, string $open, string $close): array
    {
        $start = new DateTimeImmutable($date . ' ' . $open . ':00');
        $end = new DateTimeImmutable($date . ' ' . $close . ':00');
        if ($end <= $start) {
            $end = $end->modify('+1 day');
        }
        return [$start, $end];
    }

    private function reservationWindow(array $reservation): array
    {
        $date = (string) $reservation['reservation_date'];
        $time = substr((string) $reservation['start_time'], 0, 5);
        $start = new DateTimeImmutable($date . ' ' . $time . ':00');

        // reservation_date represents the service day. If a service continues
        // after midnight (e.g. Saturday until 02:00), 00:30 belongs to the
        // following calendar day while remaining part of Saturday's planning.
        $hours = $this->openingHours($date);
        if ($hours && $hours['close'] <= $hours['open'] && $time < $hours['open']) {
            $start = $start->modify('+1 day');
        }

        $end = $start->modify('+' . (int) $reservation['duration_minutes'] . ' minutes');
        return [$start, $end];
    }

    private function overlaps(DateTimeImmutable $aStart, DateTimeImmutable $aEnd, DateTimeImmutable $bStart, DateTimeImmutable $bEnd): bool
    {
        return $aStart < $bEnd && $aEnd > $bStart;
    }

    private function assignmentConflict(array $reservation, int $tableId): ?array
    {
        [$start, $end] = $this->reservationWindow($reservation);
        $others = $this->db->fetchAll(
            "SELECT r.* FROM reservations r JOIN reservation_tables rt ON rt.reservation_id = r.id WHERE rt.table_id = ? AND r.reservation_date = ? AND r.id <> ? AND r.status NOT IN ('cancelled', 'no_show')",
            [$tableId, $reservation['reservation_date'], (int) $reservation['id']]
        );

        foreach ($others as $other) {
            [$otherStart, $otherEnd] = $this->reservationWindow($other);
            if ($this->overlaps($start, $end, $otherStart, $otherEnd)) {
                return $other;
            }
        }
        return null;
    }

    private function summary(array $reservations): array
    {
        $covers = 0;
        $unassigned = 0;
        $new = 0;
        foreach ($reservations as $reservation) {
            if (!in_array($reservation['status'], ['cancelled', 'no_show'], true)) {
                $covers += (int) $reservation['party_size'];
            }
            if (empty($reservation['tables']) && !in_array($reservation['status'], ['cancelled', 'completed', 'no_show'], true)) {
                $unassigned++;
            }
            if ($reservation['status'] === 'new') {
                $new++;
            }
        }
        return ['reservations' => count($reservations), 'covers' => $covers, 'unassigned' => $unassigned, 'new' => $new];
    }

    private function audit(string $action, string $entityType, int $entityId, array $data): void
    {
        $user = Auth::user();
        $this->db->execute(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, payload) VALUES (?, ?, ?, ?, ?)',
            [$user['id'] ?? null, $action, $entityType, $entityId, json_encode($data, JSON_UNESCAPED_UNICODE)]
        );
    }
}
