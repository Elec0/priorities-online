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
$player = require_player($db);

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body) || !isset($body['ranking']) || !is_array($body['ranking'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request body']);
    exit;
}
$ranking = array_map('intval', $body['ranking']);

// Load current round.
$row = dbx_fetch_round_by_status_for_lobby($db, $player->lobbyId, 'ranking');

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

dbx_update_round_target_ranking_to_guessing($db, $round->id, json_encode($ranking));

// Default group_ranking to dealt order if not yet set.
dbx_set_round_group_ranking_if_null($db, $round->id, json_encode($round->cardIds));

insert_system_chat($db, $player->lobbyId, "{$player->name} has submitted their ranking! Time to guess!");
bump_version($db, $round->gameId);

echo json_encode(['success' => true]);
