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

// Ensure no game exists yet for this lobby.
$stmt = $db->prepare('SELECT COUNT(*) FROM games WHERE lobby_id = :lobby_id');
$stmt->execute([':lobby_id' => $player->lobbyId]);
if ((int) $stmt->fetchColumn() > 0) {
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
$stmt2 = $db->prepare('SELECT id FROM cards');
$stmt2->execute();
$card_ids = array_column($stmt2->fetchAll(), 'id');
shuffle($card_ids);

// Deal first 5 cards.
[$dealt, $remaining] = deal_cards($card_ids);
shuffle($dealt);

$empty = empty_letters();

$db->beginTransaction();
try {
    $stmt3 = $db->prepare(
        "INSERT INTO games
         (lobby_id, current_round, target_player_index, final_decider_index,
          status, player_letters, game_letters, deck_order, state_version)
         VALUES (:lobby_id, 1, 0, 1, 'active', :pl, :gl, :deck, 1)"
    );
    $stmt3->execute([
        ':lobby_id' => $player->lobbyId,
        ':pl'       => json_encode($empty->toArray()),
        ':gl'       => json_encode($empty->toArray()),
        ':deck'     => json_encode($remaining),
    ]);
    $game_id = (int) $db->lastInsertId();

    $target_player = $active_players[0];
    $fd_player     = $active_players[1];

    // Read timer settings from lobby.
    $timer_stmt = $db->prepare('SELECT timer_enabled, timer_seconds FROM lobbies WHERE id = :id');
    $timer_stmt->execute([':id' => $player->lobbyId]);
    $timer_row     = $timer_stmt->fetch();
    $timer_enabled = $timer_row !== false && (bool) $timer_row['timer_enabled'];
    $timer_seconds = $timer_row !== false ? max(10, (int) $timer_row['timer_seconds']) : 60;
    $deadline_sql  = $timer_enabled ? "DATE_ADD(NOW(), INTERVAL {$timer_seconds} SECOND)" : 'NULL';

    $stmt4 = $db->prepare(
        "INSERT INTO rounds
         (game_id, round_number, target_player_id, final_decider_id, card_ids, status, ranking_deadline)
         VALUES (:game_id, 1, :target_id, :fd_id, :card_ids, 'ranking', {$deadline_sql})"
    );
    $stmt4->execute([
        ':game_id'   => $game_id,
        ':target_id' => $target_player->id,
        ':fd_id'     => $fd_player->id,
        ':card_ids'  => json_encode($dealt),
    ]);

    $stmt5 = $db->prepare("UPDATE lobbies SET status = 'playing' WHERE id = :id");
    $stmt5->execute([':id' => $player->lobbyId]);

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}

echo json_encode(['success' => true, 'game_id' => $game_id]);
