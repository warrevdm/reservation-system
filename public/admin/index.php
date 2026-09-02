<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
Auth::requireLogin();
$user = Auth::user();
$today = (new DateTimeImmutable())->format('Y-m-d');
$settings = [
    'booking_interval_minutes' => (int) setting('booking_interval_minutes', 30),
    'default_duration_minutes' => (int) setting('default_duration_minutes', 120),
    'max_online_party_size' => (int) setting('max_online_party_size', 12),
    'bookable_days_ahead' => (int) setting('bookable_days_ahead', 90),
    'min_lead_minutes' => (int) setting('min_lead_minutes', 60),
    'max_covers_per_slot' => (int) setting('max_covers_per_slot', 80),
    'opening_hours' => setting('opening_hours', []),
];
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#384510">
    <meta name="robots" content="noindex,nofollow">
    <title>Reservatiebeheer | De Pasto</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(base_url('/assets/css/pasto.css')) ?>">
</head>
<body class="admin-body">
<div class="admin-shell">
    <aside class="admin-sidebar">
        <div class="admin-wordmark">De Pasto</div>
        <div class="admin-tagline">Reservatiebeheer</div>
        <nav class="admin-nav">
            <button class="is-active" type="button" title="Planning">⌂ <span>Planning</span></button>
            <button type="button" id="newReservationNav" title="Nieuwe reservatie">＋ <span>Nieuwe reservatie</span></button>
            <button type="button" id="settingsNav" title="Instellingen">⚙ <span>Instellingen</span></button>
            <a href="<?= e(base_url('/')) ?>" target="_blank" rel="noopener" title="Publieke module">↗ <span>Online module</span></a>
        </nav>
        <div class="admin-sidebar-footer">
            <div class="admin-user"><strong><?= e($user['name'] ?? 'Admin') ?></strong><?= e($user['email'] ?? '') ?></div>
            <a href="<?= e(base_url('/admin/logout.php')) ?>" style="display:block;margin-top:10px;color:rgba(255,250,231,.55);font-size:10px;text-decoration:none">Uitloggen</a>
        </div>
    </aside>

    <main class="admin-main">
        <header class="admin-topbar">
            <div class="admin-title-wrap">
                <span class="eyebrow">TAFELPLAN</span>
                <h1>Dagplanning</h1>
            </div>
            <div class="admin-actions">
                <input id="adminDate" class="admin-date" type="date" value="<?= e($today) ?>">
                <button id="todayButton" class="admin-icon-btn" type="button">Vandaag</button>
                <button id="layoutButton" class="admin-icon-btn" type="button">Indeling aanpassen</button>
                <button id="addTableButton" class="admin-icon-btn" type="button">+ Tafel</button>
            </div>
        </header>

        <section class="admin-stats" aria-label="Dagoverzicht">
            <div class="stat-card"><span>Reservaties</span><strong id="statReservations">—</strong></div>
            <div class="stat-card"><span>Covers</span><strong id="statCovers">—</strong></div>
            <div class="stat-card"><span>Nog indelen</span><strong id="statUnassigned">—</strong></div>
            <div class="stat-card"><span>Nieuw online</span><strong id="statNew">—</strong></div>
        </section>

        <section class="planner-grid">
            <article class="admin-panel queue-panel">
                <div class="panel-head">
                    <h2>Reservaties</h2>
                    <span>Sleep naar tafel</span>
                </div>
                <div id="reservationQueue" class="queue-list"></div>
            </article>

            <article class="admin-panel floor-panel">
                <div class="panel-head">
                    <div id="zoneTabs" class="zone-tabs"></div>
                    <span id="floorHint">Selecteer of sleep een reservatie</span>
                </div>
                <div id="floorPlan" class="floor-wrap">
                    <span class="floor-label" id="floorLabel">Binnen</span>
                </div>
            </article>

            <article class="admin-panel detail-panel">
                <div class="panel-head"><h2>Reservatie</h2><span id="detailReference"></span></div>
                <div id="reservationDetail" class="detail-panel-body">
                    <div class="detail-placeholder">Klik op een reservatie om details te bekijken, de status aan te passen of ze opnieuw in te delen.</div>
                </div>
            </article>
        </section>
    </main>
</div>

<div id="toastStack" class="toast-stack" aria-live="polite"></div>
<div id="modalRoot"></div>

<script>
window.PASTO_ADMIN = {
    api: <?= json_encode(base_url('/admin/api.php'), JSON_UNESCAPED_SLASHES) ?>,
    csrf: <?= json_encode(csrf_token()) ?>,
    today: <?= json_encode($today) ?>,
    settings: <?= json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
};
</script>
<script src="<?= e(base_url('/assets/js/admin.js')) ?>" defer></script>
</body>
</html>
