<?php

declare(strict_types=1);

final class Auth
{
    public static function user(): ?array
    {
        if (empty($_SESSION['admin_user_id'])) {
            return null;
        }

        static $user = null;
        if ($user !== null) {
            return $user;
        }

        $user = db()->fetch(
            'SELECT id, name, email, role FROM users WHERE id = ? AND is_active = 1',
            [(int) $_SESSION['admin_user_id']]
        );

        if (!$user) {
            unset($_SESSION['admin_user_id']);
            return null;
        }

        return $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: ' . base_url('/admin/login.php'));
            exit;
        }
    }

    public static function attempt(string $email, string $password): bool
    {
        $user = db()->fetch('SELECT * FROM users WHERE LOWER(email) = LOWER(?) AND is_active = 1', [trim($email)]);
        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['admin_user_id'] = (int) $user['id'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
        db()->execute('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?', [(int) $user['id']]);
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
    }
}
