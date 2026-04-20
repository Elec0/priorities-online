<?php
declare(strict_types=1);

/** Return the PDO singleton, creating it on first call. */
function get_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    // Search for config.php: first in web root, then one level above.
    $locations = [
        __DIR__ . '/../config.php',
        __DIR__ . '/../../config.php',
    ];
    foreach ($locations as $path) {
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }

    $host = defined('DB_HOST') ? DB_HOST : 'localhost';
    $name = defined('DB_NAME') ? DB_NAME : 'priorities';
    $user = defined('DB_USER') ? DB_USER : 'priorities';
    $pass = defined('DB_PASS') ? DB_PASS : '';

    $pdo = new PDO(
        "mysql:host={$host};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

    return $pdo;
}
