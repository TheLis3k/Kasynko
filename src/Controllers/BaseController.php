<?php

namespace App\Controllers;

use App\Helpers\AuthMiddleware;

abstract class BaseController
{
    protected function view(string $template, array $data = []): void
    {
        extract($data);
        $viewFile = dirname(__DIR__, 2) . '/views/' . $template . '.phtml';
        if (!file_exists($viewFile)) {
            http_response_code(500);
            require dirname(__DIR__, 2) . '/views/errors/500.phtml';
            exit;
        }
        require $viewFile;
    }

    protected function redirect(string $url): never
    {
        redirect($url);
    }

    protected function requireCsrf(): void
    {
        csrf_verify();
    }

    protected function requireLogin(): void
    {
        AuthMiddleware::requireLogin();
    }

    protected function requireAdmin(): void
    {
        AuthMiddleware::requireRole('admin');
    }

    protected function requireCroupier(): void
    {
        AuthMiddleware::requireRole('croupier');
    }

    /** Allows both admin and croupier. */
    protected function requireAdminOrCroupier(): void
    {
        $this->requireLogin();
        $role = $_SESSION['user']['role'] ?? '';
        if ($role !== 'admin' && $role !== 'croupier') {
            http_response_code(403);
            require dirname(__DIR__, 2) . '/views/errors/403.phtml';
            exit;
        }
    }

    protected function currentUser(): ?array
    {
        return AuthMiddleware::currentUser();
    }
}
