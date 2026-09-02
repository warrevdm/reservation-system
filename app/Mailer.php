<?php

declare(strict_types=1);

final class Mailer
{
    public static function reservationCreated(array $reservation): void
    {
        if (!(bool) config('mail.enabled', false)) {
            return;
        }

        $fromEmail = (string) config('mail.from_email', 'reservaties@de-pasto.be');
        $fromName = (string) config('mail.from_name', 'De Pasto');
        $notifyEmail = (string) config('mail.notify_email', $fromEmail);
        $serviceDate = (string) $reservation['reservation_date'];
        $time = substr((string) $reservation['start_time'], 0, 5);
        $displayDate = new DateTimeImmutable($serviceDate);
        $hours = setting('opening_hours', []);
        $weekday = $displayDate->format('N');
        $serviceHours = is_array($hours) ? ($hours[$weekday] ?? null) : null;
        if (is_array($serviceHours) && !empty($serviceHours['open']) && !empty($serviceHours['close']) && $serviceHours['close'] <= $serviceHours['open'] && $time < $serviceHours['open']) {
            $displayDate = $displayDate->modify('+1 day');
        }
        $date = $displayDate->format('d/m/Y');

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $fromName . ' <' . $fromEmail . '>',
        ];

        $guestBody = "Hallo {$reservation['guest_name']},\n\n" .
            "Bedankt! We hebben je reservatie goed ontvangen.\n\n" .
            "Datum: {$date}\nTijd: {$time}\nPersonen: {$reservation['party_size']}\nReferentie: {$reservation['public_code']}\n\n" .
            "Tot snel bij De Pasto - de gezelligste huiskamer van Kapellen.\n";

        @mail((string) $reservation['guest_email'], 'Je reservatie bij De Pasto', $guestBody, implode("\r\n", $headers));

        $staffBody = "Nieuwe online reservatie\n\n" .
            "{$reservation['guest_name']} - {$reservation['party_size']} personen\n{$date} om {$time}\n" .
            "Telefoon: {$reservation['guest_phone']}\nE-mail: {$reservation['guest_email']}\n" .
            "Opmerking: " . ($reservation['notes'] ?: '-') . "\nReferentie: {$reservation['public_code']}\n";

        @mail($notifyEmail, "Nieuwe reservatie: {$reservation['guest_name']} ({$reservation['party_size']}p)", $staffBody, implode("\r\n", $headers));
    }
}
