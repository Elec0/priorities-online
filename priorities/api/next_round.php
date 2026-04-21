<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/game_logic.php';

use Priorities\Models\Round;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$db     = get_db();
$player = require_player($db);

// Load the current revealed round.
$stmt = $db->prepare(
    "SELECT r.* FROM rounds r
     JOIN games g ON g.id = r.game_id
     WHERE g.lobby_id = :lobby_id AND r.status = 'revealed'
     ORDER BY r.round_number DESC LIMIT 1"
);
$stmt->execute([':lobby_id' => $player->lobbyId]);
$row = $stmt->fetch();

if ($row === false) {
    http_response_code(400);
    echo json_encode(['error' => 'No revealed round to advance']);
    exit;
}

$round = new Round(
    id:              (int) $row['id'],
    gameId:          (int) $row['game_id'],
    roundNumber:     (int) $row['round_number'],
    targetPlayerId:  (int) $row['target_player_id'],
    finalDeciderId:  (int) $row['final_decider_id'],
    cardIds:         json_decode($row['card_ids'], true),
    targetRanking:   json_decode($row['target_ranking'], true),
    groupRanking:    $row['group_ranking'] !== null ? json_decode($row['group_ranking'], true) : null,
    result:          null,
    status:          $row['status'],
    rankingDeadline: null,
);

// Only the host or final decider may advance.
if ($player->id !== $round->finalDeciderId && !$player->isHost) {
    http_response_code(403);
    echo json_encode(['error' => 'Host or final decider only']);
    exit;
}

// Load game.
$game_stmt = $db->prepare('SELECT * FROM games WHERE id = :id LIMIT 1');
$game_stmt->execute([':id' => $round->gameId]);
$game_row = $game_stmt->fetch();

$game = hydrate_game($game_row);

// Create the next round.
if (!create_next_round($db, $game)) {
    $db->prepare("UPDATE games SET status = 'draw' WHERE id = :id")
       ->execute([':id' => $game->id]);
    insert_system_chat($db, $player->lobbyId, 'The deck ran out! The game is a draw.');
}

bump_version($db, $game->id);

echo json_encode(['success' => true]);
