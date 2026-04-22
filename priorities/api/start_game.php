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

// Ensure no game exists yet for this lobby.
if (dbx_count_games_for_lobby($db, $player->lobbyId) > 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Game already started']);
    exit;
}

$active_players = get_active_players($player->lobbyId, $db);
if (count($active_players) < 3) {
    http_response_code(400);
    echo json_encode(['error' => 'Need at least 3 players to start']);
    exit;
}

// Shuffle all card IDs.
$card_ids = dbx_all_card_ids($db);
shuffle($card_ids);

// Deal first 5 cards.
[$dealt, $remaining] = deal_cards($card_ids);
shuffle($dealt);

$empty = empty_letters();

$db->beginTransaction();
try {
    $game_id = dbx_insert_game(
        $db,
        $player->lobbyId,
        json_encode($empty->toArray()),
        json_encode($empty->toArray()),
        json_encode($remaining)
    );

    $target_player = $active_players[0];
    $fd_player     = $active_players[1];

    // Read timer settings from lobby.
    $timer_settings = dbx_lobby_timer_settings($db, $player->lobbyId);

    dbx_insert_round(
        $db,
        $game_id,
        1,
        $target_player->id,
        $fd_player->id,
        json_encode($dealt),
        $timer_settings['timer_enabled'],
        $timer_settings['timer_seconds']
    );

    dbx_set_lobby_status($db, $player->lobbyId, 'playing');

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}

echo json_encode(['success' => true, 'game_id' => $game_id]);
