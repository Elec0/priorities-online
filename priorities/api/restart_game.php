<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
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

$active_players = $db->prepare(
    "SELECT COUNT(*) FROM players WHERE lobby_id = :lobby_id AND status = 'active'"
);
$active_players->execute([':lobby_id' => $player->lobbyId]);
if ((int) $active_players->fetchColumn() < 3) {
    http_response_code(400);
    echo json_encode(['error' => 'Need at least 3 active players to restart']);
    exit;
}

$game_stmt = $db->prepare('SELECT id, status FROM games WHERE lobby_id = :lobby_id LIMIT 1');
$game_stmt->execute([':lobby_id' => $player->lobbyId]);
$game_row = $game_stmt->fetch();

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
    $db->prepare('DELETE FROM games WHERE id = :id')
       ->execute([':id' => $game_id]);

    $db->prepare("UPDATE lobbies SET status = 'waiting' WHERE id = :id")
       ->execute([':id' => $player->lobbyId]);

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}

echo json_encode(['success' => true]);
