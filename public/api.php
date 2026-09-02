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

        // Honeypots: echte bezoekers zien of vullen deze velden nooit in.
        if (!empty($input['website']) || !empty($input['company'])) {
            json_response(['ok' => true, 'reservation' => ['public_code' => 'THANKYOU']], 201);
        }

        // Een geldig formulier moet eerst via de publieke boekingspagina zijn uitgegeven
        // en minstens enkele seconden oud zijn. Dit houdt simpele POST-bots buiten.
        if (!BotProtection::verifyFormToken(isset($input['form_token']) ? (string) $input['form_token'] : null)) {
            json_response(['ok' => false, 'message' => 'Deze reservatiesessie is niet meer geldig. Vernieuw de pagina en probeer opnieuw.'], 419);
        }

        $fingerprint = hash('sha256', client_ip() . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if (!$service->publicRateLimit($fingerprint)) {
            json_response(['ok' => false, 'message' => 'Te veel pogingen. Probeer het over enkele minuten opnieuw.'], 429);
        }

        $captcha = BotProtection::verifyRecaptcha(isset($input['recaptcha_token']) ? (string) $input['recaptcha_token'] : null);
        if (!$captcha['ok']) {
            if ((bool) config('app.debug', false)) {
                json_response([
                    'ok' => false,
                    'message' => 'De anti-spamcontrole kon niet worden bevestigd.',
                    'captcha_reason' => $captcha['reason'],
                    'captcha_score' => $captcha['score'],
                ], 403);
            }
            json_response(['ok' => false, 'message' => 'De anti-spamcontrole kon niet worden bevestigd. Vernieuw de pagina en probeer opnieuw.'], 403);
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
