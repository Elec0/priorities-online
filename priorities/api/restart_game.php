<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/db_access.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$db     = get_db();
$player = require_host($db);

if (dbx_count_active_players($db, $player->lobbyId) < 3) {
    http_response_code(400);
    echo json_encode(['error' => 'Need at least 3 active players to restart']);
    exit;
}

$game_row = dbx_fetch_game_id_and_status_by_lobby($db, $player->lobbyId);

if ($game_row === false) {
    http_response_code(400);
    echo json_encode(['error' => 'No game to restart']);
    exit;
}

$game_id = (int) $game_row['id'];
$game_status = (string) $game_row['status'];
$can_restart_statuses = ['players_win', 'game_wins', 'draw'];

if (!in_array($game_status, $can_restart_statuses, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Game is still active']);
    exit;
}

$db->beginTransaction();
try {
    dbx_delete_game_by_id($db, $game_id);
    dbx_set_lobby_status($db, $player->lobbyId, 'waiting');

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}

echo json_encode(['success' => true]);
