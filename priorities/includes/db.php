<?php
/**
 * Database connection singleton.
 *
 * To configure, create a file at the path one level above the priorities/
 * directory (i.e. alongside it, outside the web root) called config.php:
 *
 *   <?php
 *   define('DB_HOST', 'localhost');
 *   define('DB_NAME', 'priorities');
 *   define('DB_USER', 'priorities');
 *   define('DB_PASS', 'your_password_here');
 *
 * If config.php is absent, the defaults below are used.
 */

$_config_path = __DIR__ . '/../../config.php';
if (file_exists($_config_path)) {
    require_once $_config_path;
}

if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'priorities');
if (!defined('DB_USER')) define('DB_USER', 'priorities');
if (!defined('DB_PASS')) define('DB_PASS', '');

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}
