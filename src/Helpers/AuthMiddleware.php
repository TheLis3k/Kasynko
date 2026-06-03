<?php

namespace App\Helpers;

class AuthMiddleware
{
    public static function requireLogin(): void
    {
        if (empty($_SESSION['user'])) {
            flash('error', t('auth.login_required'));
            redirect('/login');
        }
    }

    public static function requireRole(string $role): void
    {
        self::requireLogin();
        if (($_SESSION['user']['role'] ?? '') !== $role) {
            http_response_code(403);
            require dirname(__DIR__, 2) . '/views/errors/403.phtml';
            exit;
        }
    }

    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['user']);
    }

    public static function currentUser(): ?array
    {
        return $_SESSION['user'] ?? null;
    }
}
