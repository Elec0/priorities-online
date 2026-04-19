<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/game_logic.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $host = require_host();
    $db   = get_db();

    // Fetch lobby
    $l_stmt = $db->prepare("SELECT * FROM lobbies WHERE id = ?");
    $l_stmt->execute([$host['lobby_id']]);
    $lobby = $l_stmt->fetch();

    if ($lobby['status'] !== 'waiting') {
        http_response_code(400);
        echo json_encode(['error' => 'Game already started']);
        exit;
    }

    $active_players = get_active_players($host['lobby_id']);
    if (count($active_players) < 3) {
        http_response_code(400);
        echo json_encode(['error' => 'Need at least 3 players to start']);
        exit;
    }

    // Shuffle all card IDs for deck
    $card_stmt = $db->query("SELECT id FROM cards ORDER BY id");
    $all_ids = $card_stmt->fetchAll(PDO::FETCH_COLUMN);
    shuffle($all_ids);
    $deck_order = array_values($all_ids);

    $empty = empty_letters();
    $player_letters = $empty;
    $game_letters   = $empty;

    // target starts at index 0
    $target_idx = 0;
    $target = $active_players[$target_idx];

    // FD is next active player after target, skipping the target's turn_order
    $fd_idx = next_active_player_index($active_players, $target_idx, (int)$target['turn_order']);
    $fd = $active_players[$fd_idx];

    // Deal 5 cards
    [$dealt, $remaining_deck] = deal_cards($deck_order, 5);

    $db->prepare(
        "INSERT INTO games (lobby_id, current_round, target_player_index, final_decider_index,
         status, player_letters, game_letters, deck_order, state_version)
         VALUES (?, 1, ?, ?, 'active', ?, ?, ?, 1)"
    )->execute([
        $host['lobby_id'],
        $target_idx,
        $fd_idx,
        json_encode($player_letters),
        json_encode($game_letters),
        json_encode($remaining_deck),
    ]);
    $game_id = (int)$db->lastInsertId();

    $db->prepare(
        "INSERT INTO rounds (game_id, round_number, target_player_id, final_decider_id, card_ids, status, ranking_deadline)
         VALUES (?, 1, ?, ?, ?, 'ranking', DATE_ADD(NOW(), INTERVAL 60 SECOND))"
    )->execute([
        $game_id,
        (int)$target['id'],
        (int)$fd['id'],
        json_encode($dealt),
    ]);

    $db->prepare("UPDATE lobbies SET status = 'playing', updated_at = NOW() WHERE id = ?")
       ->execute([$host['lobby_id']]);

    insert_system_chat($db, $host['lobby_id'], "Game started! {$target['name']} is ranking their cards…");

    echo json_encode(['success' => true, 'game_id' => $game_id]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
