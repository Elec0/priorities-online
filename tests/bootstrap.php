<?php
declare(strict_types=1);

// Stub DB constants so game_logic.php can be loaded without a real DB.
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'priorities');
if (!defined('DB_USER')) define('DB_USER', 'priorities');
if (!defined('DB_PASS')) define('DB_PASS', '');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../priorities/includes/game_logic.php';
