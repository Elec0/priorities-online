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

// APCu rate limit: 10 requests per token per 10 seconds.
if (function_exists('apcu_fetch') && function_exists('apcu_store')) {
    $rate_key = 'rate:update_guess:' . $player->sessionToken;
    $count    = (int) apcu_fetch($rate_key);
    if ($count >= 10) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many requests']);
        exit;
    }
    apcu_store($rate_key, $count + 1, 10);
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body) || !isset($body['ranking']) || !is_array($body['ranking'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request body']);
    exit;
}
$ranking = array_map('intval', $body['ranking']);

// Load current guessing round.
$row = dbx_fetch_round_by_status_for_lobby($db, $player->lobbyId, 'guessing');

if ($row === false) {
    http_response_code(400);
    echo json_encode(['error' => 'No active guessing round']);
    exit;
}

$card_ids = array_map('intval', json_decode($row['card_ids'], true));
$target_player_id = (int) $row['target_player_id'];

// Target player cannot update group guess.
if ($player->id === $target_player_id) {
    http_response_code(403);
    echo json_encode(['error' => 'Target player cannot update group guess']);
    exit;
}

// Validate card IDs.
$submitted_sorted = $ranking;
$dealt_sorted     = $card_ids;
sort($submitted_sorted);
sort($dealt_sorted);
if ($submitted_sorted !== $dealt_sorted) {
    http_response_code(400);
    echo json_encode(['error' => 'Ranking must contain exactly the dealt cards']);
    exit;
}

dbx_update_round_group_ranking($db, (int) $row['id'], json_encode($ranking));

bump_version($db, (int) $row['game_id']);

echo json_encode(['success' => true]);
