<?php

/**
 * Global helper functions — t(), e(), flash(), old(), redirect(), csrf_token(), csrf_verify().
 * Loaded once by the autoloader bootstrap in public/index.php.
 */

function t(string $key, array $replace = []): string
{
    static $translations = [];
    $lang = $_SESSION['lang'] ?? 'pl';

    if (empty($translations[$lang])) {
        $file = dirname(__DIR__, 2) . "/lang/{$lang}.php";
        $translations[$lang] = file_exists($file) ? require $file : [];
    }

    $value = $translations[$lang][$key] ?? $key;

    foreach ($replace as $k => $v) {
        $value = str_replace(':' . $k, $v, $value);
    }

    return $value;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function old(string $key, string $default = ''): string
{
    return e($_SESSION['old'][$key] ?? $default);
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        require dirname(__DIR__, 2) . '/views/errors/403.phtml';
        exit;
    }
}

function base_url(string $path = ''): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/' . ltrim($path, '/');
}
