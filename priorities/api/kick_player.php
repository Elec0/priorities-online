<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/db_access.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/game_logic.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$db     = get_db();
$player = require_host($db);

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body) || !isset($body['player_id']) || !is_int($body['player_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request body']);
    exit;
}

$target_id = $body['player_id'];

if ($target_id === $player->id) {
    http_response_code(400);
    echo json_encode(['error' => 'Host cannot kick themselves']);
    exit;
}

// Verify target is in the same lobby.
$target = dbx_find_active_player_in_lobby($db, $target_id, $player->lobbyId);

if ($target === false) {
    http_response_code(404);
    echo json_encode(['error' => 'Player not found in your lobby']);
    exit;
}

dbx_mark_player_kicked($db, $target_id);

insert_system_chat($db, $player->lobbyId, "{$target['name']} was removed from the lobby.");

// Bump version if game exists.
$game_id = dbx_get_game_id_by_lobby($db, $player->lobbyId);
if ($game_id !== null) {
    bump_version($db, $game_id);
}

echo json_encode(['success' => true]);
