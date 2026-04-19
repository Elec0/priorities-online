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
    $player = validate_token();
    $db     = get_db();

    $g_stmt = $db->prepare(
        "SELECT g.* FROM games g
         JOIN lobbies l ON l.id = g.lobby_id
         WHERE g.lobby_id = ? AND l.status = 'playing'"
    );
    $g_stmt->execute([$player['lobby_id']]);
    $game = $g_stmt->fetch();
    if (!$game) {
        http_response_code(400);
        echo json_encode(['error' => 'No active game found']);
        exit;
    }

    $round = get_current_round((int)$game['id']);
    if ($round['status'] !== 'guessing') {
        http_response_code(400);
        echo json_encode(['error' => 'Not in guessing phase']);
        exit;
    }

    if ((int)$player['id'] !== (int)$round['final_decider_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Only the Final Decider can lock in the guess']);
        exit;
    }

    if ($round['group_ranking'] === null) {
        http_response_code(400);
        echo json_encode(['error' => 'No group ranking has been set yet']);
        exit;
    }

    $target_ranking = json_decode($round['target_ranking'], true);
    $group_ranking  = json_decode($round['group_ranking'],  true);
    $results        = score_round($target_ranking, $group_ranking);

    $player_won_ids = [];
    $game_won_ids   = [];
    foreach ($results as $r) {
        if ($r['correct']) {
            $player_won_ids[] = $r['card_id'];
        } else {
            $game_won_ids[] = $r['card_id'];
        }
    }

    $player_letters = json_decode($game['player_letters'], true);
    $game_letters   = json_decode($game['game_letters'],   true);

    $player_letters = award_letters($player_letters, $player_won_ids, $db);
    $game_letters   = award_letters($game_letters,   $game_won_ids,   $db);

    // Update round
    $db->prepare("UPDATE rounds SET result = ?, status = 'revealed' WHERE id = ?")
       ->execute([json_encode($results), $round['id']]);

    // Determine game status
    $game_status = 'active';
    if (check_win($player_letters)) {
        $game_status = 'players_win';
    } elseif (check_win($game_letters)) {
        $game_status = 'game_wins';
    }

    $db->prepare(
        "UPDATE games SET player_letters = ?, game_letters = ?, status = ? WHERE id = ?"
    )->execute([
        json_encode($player_letters),
        json_encode($game_letters),
        $game_status,
        $game['id'],
    ]);

    // Build summary message
    $correct_count = count($player_won_ids);
    $p_won_letters = array_map(fn($id) => '', $player_won_ids); // will fetch below
    $g_won_letters = [];
    if (!empty($player_won_ids) || !empty($game_won_ids)) {
        $all_won = array_merge($player_won_ids, $game_won_ids);
        $placeholders = implode(',', array_fill(0, count($all_won), '?'));
        $ltr_stmt = $db->prepare("SELECT id, letter FROM cards WHERE id IN ($placeholders)");
        $ltr_stmt->execute($all_won);
        $id_to_letter = [];
        while ($row = $ltr_stmt->fetch()) {
            $id_to_letter[(int)$row['id']] = $row['letter'];
        }
        $p_won_letters = array_map(fn($id) => $id_to_letter[$id] ?? '?', $player_won_ids);
        $g_won_letters = array_map(fn($id) => $id_to_letter[$id] ?? '?', $game_won_ids);
    }

    $p_str = empty($p_won_letters) ? 'none' : implode(',', $p_won_letters);
    $g_str = empty($g_won_letters) ? 'none' : implode(',', $g_won_letters);
    $round_num = (int)$round['round_number'];

    insert_system_chat(
        $db,
        $player['lobby_id'],
        "Round {$round_num} revealed: {$correct_count}/5 correct! Players won: {$p_str} | Game won: {$g_str}"
    );

    // Create next round if game is still active
    if ($game_status === 'active') {
        $active_players = get_active_players($player['lobby_id']);
        $target_idx = (int)$game['target_player_index'];
        $fd_idx     = (int)$game['final_decider_index'];

        $new_target_idx = next_active_player_index($active_players, $target_idx, -1);
        $new_target = $active_players[$new_target_idx];

        $new_fd_idx = next_active_player_index($active_players, $fd_idx, (int)$new_target['turn_order']);
        $new_fd     = $active_players[$new_fd_idx];

        // Reload deck_order (game was updated above but not fetched)
        $deck_stmt = $db->prepare("SELECT deck_order FROM games WHERE id = ?");
        $deck_stmt->execute([$game['id']]);
        $current_deck = json_decode($deck_stmt->fetchColumn(), true);

        if (count($current_deck) < 5) {
            // Deck exhausted without a win → draw
            $db->prepare("UPDATE games SET status = 'draw' WHERE id = ?")->execute([$game['id']]);
            insert_system_chat($db, $player['lobby_id'], "The deck is empty — it's a draw!");
        } else {
            [$dealt, $remaining] = deal_cards($current_deck, 5);
            $new_round_number = (int)$game['current_round'] + 1;

            $db->prepare(
                "INSERT INTO rounds (game_id, round_number, target_player_id, final_decider_id, card_ids, status, ranking_deadline)
                 VALUES (?, ?, ?, ?, ?, 'ranking', DATE_ADD(NOW(), INTERVAL 60 SECOND))"
            )->execute([
                $game['id'],
                $new_round_number,
                (int)$new_target['id'],
                (int)$new_fd['id'],
                json_encode($dealt),
            ]);

            $db->prepare(
                "UPDATE games SET current_round = ?, target_player_index = ?, final_decider_index = ?, deck_order = ? WHERE id = ?"
            )->execute([
                $new_round_number,
                $new_target_idx,
                $new_fd_idx,
                json_encode($remaining),
                $game['id'],
            ]);

            insert_system_chat(
                $db,
                $player['lobby_id'],
                "Round {$new_round_number} started! {$new_target['name']} is ranking their cards…"
            );
        }
    }

    bump_version($db, (int)$game['id']);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
