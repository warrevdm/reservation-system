<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';

$daysAhead = max(1, (int) setting('bookable_days_ahead', 90));
$maxParty = max(1, (int) setting('max_online_party_size', 12));
$formToken = BotProtection::issueFormToken();
$recaptchaEnabled = BotProtection::recaptchaEnabled();
$recaptchaSiteKey = (string) config('security.recaptcha.site_key', '');
$recaptchaAction = (string) config('security.recaptcha.action', 'reservation');
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#384510">
    <meta name="robots" content="index,follow">
    <title>Reserveer je tafel | De Pasto</title>
    <meta name="description" content="Reserveer je tafel bij De Pasto, de gezelligste huiskamer van Kapellen.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(base_url('/assets/css/pasto.css')) ?>">
</head>
<body class="booking-page">
    <main class="booking-shell">
        <section class="booking-brand" aria-labelledby="booking-title">
            <div class="brand-kicker">DE GEZELLIGSTE HUISKAMER VAN KAPELLEN</div>
            <a class="wordmark" href="https://www.de-pasto.be" aria-label="De Pasto">De Pasto</a>
            <div class="brand-copy">
                <span class="eyebrow">RESERVEER JE TAFEL</span>
                <h1 id="booking-title">Schuif gezellig aan.</h1>
                <p>Kies je moment, laat je gegevens achter en wij houden met plezier een plekje voor je vrij.</p>
            </div>
            <div class="brand-note">
                <span class="brand-note-dot"></span>
                <span>Dorpsstraat 45 · 2950 Kapellen</span>
            </div>
        </section>

        <section class="booking-panel" aria-label="Reservatieformulier">
            <div class="booking-progress" aria-hidden="true">
                <span class="progress-dot is-active" data-progress="1">1</span>
                <span class="progress-line"></span>
                <span class="progress-dot" data-progress="2">2</span>
                <span class="progress-line"></span>
                <span class="progress-dot" data-progress="3">3</span>
            </div>

            <form id="bookingForm" novalidate data-days-ahead="<?= $daysAhead ?>" data-max-party="<?= $maxParty ?>">
                <section class="booking-step is-active" data-step="1">
                    <div class="step-heading">
                        <span class="eyebrow">STAP 1</span>
                        <h2>Wanneer kom je langs?</h2>
                    </div>

                    <div class="field-group">
                        <label for="reservationDate">Datum</label>
                        <input class="input" type="date" id="reservationDate" name="date" required>
                        <div id="dateShortcuts" class="date-shortcuts"></div>
                    </div>

                    <div class="field-group">
                        <label>Aantal personen</label>
                        <div id="partySizeButtons" class="party-grid" role="group" aria-label="Aantal personen"></div>
                        <input type="hidden" id="partySize" name="party_size" value="2">
                    </div>

                    <div class="step-actions">
                        <button class="btn btn-primary btn-wide" type="button" data-next="2">Bekijk beschikbare uren</button>
                    </div>
                </section>

                <section class="booking-step" data-step="2">
                    <button type="button" class="text-button back-button" data-back="1">← Terug</button>
                    <div class="step-heading">
                        <span class="eyebrow">STAP 2</span>
                        <h2>Kies een uur.</h2>
                        <p class="step-subtitle" id="slotSummary"></p>
                    </div>

                    <div id="slotState" class="slot-state is-loading" hidden>
                        <span class="loader"></span>
                        <span>Even kijken wat nog vrij is…</span>
                    </div>
                    <div id="timeSlots" class="time-grid"></div>
                    <input type="hidden" id="reservationTime" name="time" required>

                    <div class="step-actions">
                        <button class="btn btn-primary btn-wide" type="button" data-next="3" disabled id="toDetails">Verder met je gegevens</button>
                    </div>
                </section>

                <section class="booking-step" data-step="3">
                    <button type="button" class="text-button back-button" data-back="2">← Terug</button>
                    <div class="step-heading">
                        <span class="eyebrow">STAP 3</span>
                        <h2>Bijna geregeld.</h2>
                        <p class="step-subtitle" id="finalSummary"></p>
                    </div>

                    <div class="form-grid">
                        <div class="field-group field-span-2">
                            <label for="guestName">Naam</label>
                            <input class="input" type="text" id="guestName" name="name" autocomplete="name" maxlength="100" required placeholder="Voor- en achternaam">
                        </div>
                        <div class="field-group">
                            <label for="guestEmail">E-mail</label>
                            <input class="input" type="email" id="guestEmail" name="email" autocomplete="email" maxlength="190" required placeholder="jij@email.be">
                        </div>
                        <div class="field-group">
                            <label for="guestPhone">Telefoon</label>
                            <input class="input" type="tel" id="guestPhone" name="phone" autocomplete="tel" maxlength="30" required placeholder="04xx xx xx xx">
                        </div>
                        <div class="field-group field-span-2">
                            <label for="guestNotes">Iets dat we mogen weten? <span class="optional">optioneel</span></label>
                            <textarea class="input textarea" id="guestNotes" name="notes" maxlength="1000" rows="4" placeholder="Kinderstoel, allergie, verjaardag…"></textarea>
                        </div>
                    </div>

                    <input type="hidden" name="form_token" value="<?= e($formToken) ?>">
                    <input class="honeypot" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <input class="honeypot" type="text" name="company" tabindex="-1" autocomplete="off" aria-hidden="true">

                    <label class="consent-row">
                        <input type="checkbox" id="privacyConsent" required>
                        <span>Ik ga ermee akkoord dat De Pasto mijn gegevens gebruikt om deze reservatie te verwerken.</span>
                    </label>

                    <div id="formError" class="form-error" role="alert" hidden></div>
                    <div class="step-actions">
                        <button class="btn btn-primary btn-wide" type="submit" id="submitBooking">Reserveer mijn tafel</button>
                    </div>
                </section>
            </form>

            <section id="bookingSuccess" class="booking-success" hidden>
                <div class="success-mark">✓</div>
                <span class="eyebrow">GELUKT</span>
                <h2>Tot snel bij De Pasto.</h2>
                <p>Je reservatie is goed ontvangen. Bewaar je referentie voor het geval je ons nog contacteert.</p>
                <div class="reference-card">
                    <span>Referentie</span>
                    <strong id="successCode">—</strong>
                </div>
                <div id="successSummary" class="success-summary"></div>
                <button class="btn btn-secondary btn-wide" type="button" id="newBooking">Nog een reservatie maken</button>
            </section>

            <footer class="booking-footer">De Pasto · warm in sfeer, duidelijk in organisatie.</footer>
        </section>
    </main>

    <script>
        window.PASTO_API = <?= json_encode(base_url('/api.php'), JSON_UNESCAPED_SLASHES) ?>;
        window.PASTO_RECAPTCHA = <?= json_encode([
            'enabled' => $recaptchaEnabled,
            'siteKey' => $recaptchaSiteKey,
            'action' => $recaptchaAction,
        ], JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <?php if ($recaptchaEnabled): ?>
        <script src="https://www.google.com/recaptcha/api.js?render=<?= e(rawurlencode($recaptchaSiteKey)) ?>" defer></script>
    <?php endif; ?>
    <script src="<?= e(base_url('/assets/js/booking.js')) ?>" defer></script>
</body>
</html>
