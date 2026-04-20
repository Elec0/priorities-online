<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
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
$stmt = $db->prepare(
    "SELECT id, name FROM players WHERE id = :id AND lobby_id = :lobby_id AND status = 'active' LIMIT 1"
);
$stmt->execute([':id' => $target_id, ':lobby_id' => $player->lobbyId]);
$target = $stmt->fetch();

if ($target === false) {
    http_response_code(404);
    echo json_encode(['error' => 'Player not found in your lobby']);
    exit;
}

$stmt2 = $db->prepare("UPDATE players SET status = 'kicked' WHERE id = :id");
$stmt2->execute([':id' => $target_id]);

insert_system_chat($db, $player->lobbyId, "{$target['name']} was removed from the lobby.");

// Bump version if game exists.
$game_stmt = $db->prepare('SELECT id FROM games WHERE lobby_id = :id LIMIT 1');
$game_stmt->execute([':id' => $player->lobbyId]);
$game_row = $game_stmt->fetch();
if ($game_row !== false) {
    bump_version($db, (int) $game_row['id']);
}

echo json_encode(['success' => true]);
