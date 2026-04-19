<?php
/**
 * Test bootstrap — loads game logic without requiring a live DB connection.
 *
 * db.php defines get_db() as a lazy singleton; it only opens a connection
 * when first called. Pure-function tests never invoke get_db(), so no
 * connection is attempted. Tests that exercise DB-dependent code should
 * pass a mock PDO directly.
 */

// Pre-define DB constants so db.php skips its config file lookup.
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'test');
if (!defined('DB_USER')) define('DB_USER', 'test');
if (!defined('DB_PASS')) define('DB_PASS', '');

require_once __DIR__ . '/../priorities/includes/game_logic.php';
