<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
Auth::logout();
header('Location: ' . base_url('/admin/login.php'));
exit;
