<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

if (Auth::check()) {
    header('Location: ' . base_url('/admin/'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['_csrf'] ?? '');
    if (!hash_equals(csrf_token(), $token)) {
        $error = 'Je sessie is verlopen. Probeer opnieuw.';
    } elseif (!Auth::attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
        $error = 'E-mailadres of wachtwoord is niet correct.';
    } else {
        header('Location: ' . base_url('/admin/'));
        exit;
    }
}
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#384510">
    <title>Beheer | De Pasto Reservaties</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(base_url('/assets/css/pasto.css')) ?>">
</head>
<body class="login-page">
    <main class="login-card">
        <div class="wordmark">De Pasto</div>
        <span class="eyebrow" style="color:#a2b470;font-size:9px">RESERVATIEBEHEER</span>
        <h1>Welkom terug.</h1>
        <p>Log in om reservaties te verdelen over het tafelplan en de dagplanning te beheren.</p>

        <?php if ($error !== ''): ?>
            <div class="login-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="on">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <div class="field-group">
                <label for="email">E-mail</label>
                <input class="input" id="email" type="email" name="email" autocomplete="username" required autofocus>
            </div>
            <div class="field-group">
                <label for="password">Wachtwoord</label>
                <input class="input" id="password" type="password" name="password" autocomplete="current-password" required>
            </div>
            <button class="btn btn-primary btn-wide" type="submit">Inloggen</button>
        </form>
    </main>
</body>
</html>
