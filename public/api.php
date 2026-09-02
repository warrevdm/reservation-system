<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';

$service = new ReservationService();
$action = (string) ($_GET['action'] ?? '');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'availability') {
        $date = trim((string) ($_GET['date'] ?? ''));
        $partySize = (int) ($_GET['party_size'] ?? 0);
        if (!is_valid_date($date) || $partySize < 1) {
            json_response(['ok' => false, 'message' => 'Ongeldige datum of groepsgrootte.'], 422);
        }

        $hours = $service->openingHours($date);
        json_response([
            'ok' => true,
            'closed' => $hours === null,
            'opening_hours' => $hours,
            'slots' => $service->availableSlots($date, $partySize),
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'reserve') {
        $input = request_json();
        if (!empty($input['website'])) {
            json_response(['ok' => true, 'reservation' => ['public_code' => 'THANKYOU']], 201);
        }

        $fingerprint = hash('sha256', client_ip() . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if (!$service->publicRateLimit($fingerprint)) {
            json_response(['ok' => false, 'message' => 'Te veel pogingen. Probeer het over enkele minuten opnieuw.'], 429);
        }

        if (empty($input['privacy_consent'])) {
            json_response(['ok' => false, 'message' => 'Bevestig eerst dat we je gegevens voor de reservatie mogen gebruiken.'], 422);
        }

        $reservation = $service->create($input);
        Mailer::reservationCreated($reservation);
        json_response(['ok' => true, 'reservation' => $reservation], 201);
    }

    json_response(['ok' => false, 'message' => 'Endpoint niet gevonden.'], 404);
} catch (InvalidArgumentException $e) {
    json_response(['ok' => false, 'message' => $e->getMessage()], 422);
} catch (RuntimeException $e) {
    json_response(['ok' => false, 'message' => $e->getMessage()], 409);
} catch (Throwable $e) {
    if ((bool) config('app.debug', false)) {
        json_response(['ok' => false, 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
    }
    json_response(['ok' => false, 'message' => 'Er liep iets mis. Probeer opnieuw of neem contact op met De Pasto.'], 500);
}
