<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

// PSR-4-style autoloader
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = BASE_PATH . '/src/' . $relative . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

require BASE_PATH . '/src/Helpers/helpers.php';

// Session
session_start();

// Bootstrap language
$lang = $_GET['_lang'] ?? $_SESSION['lang'] ?? 'pl';
$_SESSION['lang'] = in_array($lang, ['pl', 'en'], true) ? $lang : 'pl';

// Global error handler — never expose stack traces
set_exception_handler(function (Throwable $e) {
    error_log((string) $e);
    http_response_code(500);
    if (!headers_sent()) {
        require BASE_PATH . '/views/errors/500.phtml';
    }
    exit;
});

// ===== Routes =====
use App\Helpers\Router;
use App\Controllers\{
    AuthController, ProfileController, RouletteController,
    SlotController, BetController, AdminController, ErrorController
};

$router = new Router();

// Language switch
$router->get('/lang/{code}', function (array $p): void {
    $code = in_array($p['code'], ['pl', 'en'], true) ? $p['code'] : 'pl';
    $_SESSION['lang'] = $code;

    if (\App\Helpers\AuthMiddleware::isLoggedIn()) {
        // persist to DB
        $user = new \App\Models\User(\App\Helpers\Database::getInstance());
        $user->updateLang((int)$_SESSION['user']['id'], $code);
        $_SESSION['user']['lang_preference'] = $code;
    }

    redirect($_SERVER['HTTP_REFERER'] ?? '/');
});

// Auth
$router->get('/login',    fn() => (new AuthController())->showLogin());
$router->post('/login',   fn() => (new AuthController())->login());
$router->get('/register', fn() => (new AuthController())->showRegister());
$router->post('/register',fn() => (new AuthController())->register());
$router->post('/logout',  fn() => (new AuthController())->logout());

// Home → redirect based on login state
$router->get('/', function (): void {
    if (\App\Helpers\AuthMiddleware::isLoggedIn()) {
        redirect('/bets');
    } else {
        redirect('/login');
    }
});

// Profile
$router->get('/profile',            fn() => (new ProfileController())->show());
$router->post('/profile',           fn() => (new ProfileController())->update());
$router->post('/profile/delete',    fn() => (new ProfileController())->delete());
$router->get('/profile/avatar/{filename}', fn(array $p) => (new ProfileController())->serveAvatar($p['filename']));

// Games
$router->get('/roulette',  fn() => (new RouletteController())->show());
$router->post('/roulette', fn() => (new RouletteController())->play());
$router->get('/slots',     fn() => (new SlotController())->show());
$router->post('/slots',    fn() => (new SlotController())->play());

// Bets CRUD
$router->get('/bets',              fn() => (new BetController())->index());
$router->get('/bets/create',       fn() => (new BetController())->create());
$router->post('/bets/store',       fn() => (new BetController())->store());
$router->get('/bets/{id}/edit',    fn(array $p) => (new BetController())->edit((int)$p['id']));
$router->post('/bets/{id}/update', fn(array $p) => (new BetController())->update((int)$p['id']));
$router->post('/bets/{id}/delete', fn(array $p) => (new BetController())->delete((int)$p['id']));
$router->get('/bets/export',       fn() => (new BetController())->export());

// Admin
$router->get('/admin',         fn() => (new AdminController())->dashboard());
$router->get('/admin/reports', fn() => (new AdminController())->reports());

// Dispatch
$router->dispatch(
    $_SERVER['REQUEST_METHOD'],
    $_SERVER['REQUEST_URI']
);
