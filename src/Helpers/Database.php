<?php

namespace App\Helpers;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $cfg = require dirname(__DIR__, 2) . '/config/config.php';
            $db  = $cfg['db'];
            $dsn = "pgsql:host={$db['host']};port={$db['port']};dbname={$db['name']}";

            try {
                self::$instance = new PDO($dsn, $db['user'], $db['pass'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                error_log('DB connection failed: ' . $e->getMessage());
                http_response_code(500);
                $lang = $_SESSION['lang'] ?? 'pl';
                require dirname(__DIR__, 2) . '/views/errors/500.phtml';
                exit;
            }
        }

        return self::$instance;
    }

    private function __construct() {}
    private function __clone() {}
}
