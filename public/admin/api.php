<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
Auth::requireLogin();

$service = new ReservationService();
$action = (string) ($_GET['action'] ?? '');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'day') {
        $date = trim((string) ($_GET['date'] ?? (new DateTimeImmutable())->format('Y-m-d')));
        $data = $service->dayData($date);
        json_response(['ok' => true, 'data' => $data]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $input = request_json();

        if ($action === 'assign') {
            $result = $service->assign((int) ($input['reservation_id'] ?? 0), (int) ($input['table_id'] ?? 0));
            json_response(['ok' => true] + $result);
        }
        if ($action === 'unassign') {
            $service->unassign((int) ($input['reservation_id'] ?? 0));
            json_response(['ok' => true]);
        }
        if ($action === 'status') {
            $service->updateStatus((int) ($input['reservation_id'] ?? 0), (string) ($input['status'] ?? ''));
            json_response(['ok' => true]);
        }
        if ($action === 'reservation_save') {
            $reservation = $service->saveReservation($input);
            json_response(['ok' => true, 'reservation' => $reservation]);
        }
        if ($action === 'table_position') {
            $service->saveTablePosition((int) ($input['table_id'] ?? 0), (float) ($input['x'] ?? 0), (float) ($input['y'] ?? 0));
            json_response(['ok' => true]);
        }
        if ($action === 'table_save') {
            $table = $service->saveTable($input);
            json_response(['ok' => true, 'table' => $table]);
        }
        if ($action === 'settings_save') {
            $service->saveSettings($input);
            json_response(['ok' => true]);
        }
    }

    json_response(['ok' => false, 'message' => 'Endpoint niet gevonden.'], 404);
} catch (InvalidArgumentException $e) {
    json_response(['ok' => false, 'message' => $e->getMessage()], 422);
} catch (RuntimeException $e) {
    json_response(['ok' => false, 'message' => $e->getMessage()], 409);
} catch (Throwable $e) {
    if ((bool) config('app.debug', false)) {
        json_response(['ok' => false, 'message' => $e->getMessage()], 500);
    }
    json_response(['ok' => false, 'message' => 'Er liep iets mis in het beheer.'], 500);
}
