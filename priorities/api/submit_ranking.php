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
$player = require_player($db);

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body) || !isset($body['ranking']) || !is_array($body['ranking'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request body']);
    exit;
}
$ranking = array_map('intval', $body['ranking']);

// Load current round.
$stmt = $db->prepare(
    "SELECT r.* FROM rounds r
     JOIN games g ON g.id = r.game_id
     WHERE g.lobby_id = :lobby_id AND r.status = 'ranking'
    ORDER BY g.id DESC, r.round_number DESC LIMIT 1"
);
$stmt->execute([':lobby_id' => $player->lobbyId]);
$row = $stmt->fetch();

if ($row === false) {
    http_response_code(400);
    echo json_encode(['error' => 'No active ranking round']);
    exit;
}

use Priorities\Models\Round;
$round = new Round(
    id:              (int) $row['id'],
    gameId:          (int) $row['game_id'],
    roundNumber:     (int) $row['round_number'],
    targetPlayerId:  (int) $row['target_player_id'],
    finalDeciderId:  (int) $row['final_decider_id'],
    cardIds:         array_map('intval', json_decode($row['card_ids'], true)),
    targetRanking:   null,
    groupRanking:    null,
    result:          null,
    status:          $row['status'],
    rankingDeadline: $row['ranking_deadline'],
);

require_is_target($player, $round);

// Validate submitted IDs match the dealt cards.
$submitted_sorted = $ranking;
$dealt_sorted     = $round->cardIds;
sort($submitted_sorted);
sort($dealt_sorted);
if ($submitted_sorted !== $dealt_sorted) {
    http_response_code(400);
    echo json_encode(['error' => 'Ranking must contain exactly the dealt cards']);
    exit;
}

$stmt2 = $db->prepare(
    "UPDATE rounds SET target_ranking = :ranking, status = 'guessing', ranking_deadline = NULL
     WHERE id = :id"
);
$stmt2->execute([':ranking' => json_encode($ranking), ':id' => $round->id]);

// Default group_ranking to dealt order if not yet set.
$stmt3 = $db->prepare(
    'UPDATE rounds SET group_ranking = :gr WHERE id = :id AND group_ranking IS NULL'
);
$stmt3->execute([':gr' => json_encode($round->cardIds), ':id' => $round->id]);

$game_stmt = $db->prepare('SELECT id, lobby_id FROM games WHERE id = :id LIMIT 1');
$game_stmt->execute([':id' => $round->gameId]);
$game_row = $game_stmt->fetch();

insert_system_chat($db, $game_row['lobby_id'], "{$player->name} has submitted their ranking! Time to guess!");
bump_version($db, $round->gameId);

echo json_encode(['success' => true]);
