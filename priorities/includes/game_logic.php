<?php
require_once __DIR__ . '/db.php';

/**
 * Returns active players for a lobby sorted by turn_order ASC.
 */
function get_active_players(int $lobby_id): array {
    $db = get_db();
    $stmt = $db->prepare(
        "SELECT * FROM players WHERE lobby_id = ? AND status = 'active' ORDER BY turn_order ASC"
    );
    $stmt->execute([$lobby_id]);
    return $stmt->fetchAll();
}

/**
 * Given an array of active players (0-indexed by position in $active_players),
 * returns the index of the next player after $current_index, skipping anyone
 * whose turn_order equals $skip_turn_order (-1 means skip nobody).
 * Wraps around. Edge case: if only one player and must be skipped, still returns 0.
 */
function next_active_player_index(array $active_players, int $current_index, int $skip_turn_order = -1): int {
    $count = count($active_players);
    if ($count === 0) return 0;
    if ($count === 1) return 0;

    $idx = ($current_index + 1) % $count;
    $attempts = 0;
    while ($attempts < $count) {
        if ($skip_turn_order === -1 || (int)$active_players[$idx]['turn_order'] !== $skip_turn_order) {
            return $idx;
        }
        $idx = ($idx + 1) % $count;
        $attempts++;
    }
    // All players match the skip constraint — return next anyway
    return ($current_index + 1) % $count;
}

/**
 * Scores a round by comparing target_ranking and group_ranking.
 * Both are arrays of 5 card IDs ordered by position (index 0 = position 1).
 * Returns array of 5 items: [['card_id'=>int,'correct'=>bool], ...]
 */
function score_round(array $target_ranking, array $group_ranking): array {
    $results = [];
    for ($i = 0; $i < 5; $i++) {
        $results[] = [
            'card_id' => (int)$target_ranking[$i],
            'correct' => ((int)$target_ranking[$i] === (int)$group_ranking[$i]),
        ];
    }
    return $results;
}

/**
 * Returns true if the letters array meets the win condition:
 * P>=1, R>=2, I>=3, O>=1, T>=1, E>=1, S>=1
 */
function check_win(array $letters): bool {
    return (
        ($letters['P'] ?? 0) >= 1 &&
        ($letters['R'] ?? 0) >= 2 &&
        ($letters['I'] ?? 0) >= 3 &&
        ($letters['O'] ?? 0) >= 1 &&
        ($letters['T'] ?? 0) >= 1 &&
        ($letters['E'] ?? 0) >= 1 &&
        ($letters['S'] ?? 0) >= 1
    );
}

/**
 * Returns the initial empty letters array.
 */
function empty_letters(): array {
    return ['P' => 0, 'R' => 0, 'I' => 0, 'O' => 0, 'T' => 0, 'E' => 0, 'S' => 0];
}

/**
 * Increments letter counts in $current_letters for each card won.
 * Looks up card.letter from DB. Returns updated letters array.
 */
function award_letters(array $current_letters, array $won_card_ids, PDO $db): array {
    if (empty($won_card_ids)) return $current_letters;
    $placeholders = implode(',', array_fill(0, count($won_card_ids), '?'));
    $stmt = $db->prepare("SELECT letter FROM cards WHERE id IN ($placeholders)");
    $stmt->execute($won_card_ids);
    while ($row = $stmt->fetch()) {
        $letter = $row['letter'];
        if (isset($current_letters[$letter])) {
            $current_letters[$letter]++;
        }
    }
    return $current_letters;
}

/**
 * Bumps the state_version of a game by 1.
 */
function bump_version(PDO $db, int $game_id): void {
    $db->prepare("UPDATE games SET state_version = state_version + 1 WHERE id = ?")->execute([$game_id]);
}

/**
 * Inserts a system chat message (player_id = NULL).
 */
function insert_system_chat(PDO $db, int $lobby_id, string $message): void {
    $db->prepare("INSERT INTO chat_messages (lobby_id, player_id, message) VALUES (?, NULL, ?)")
       ->execute([$lobby_id, $message]);
}

/**
 * Deals $count cards from the front of deck_order, returns [dealt_ids, remaining_deck].
 */
function deal_cards(array $deck_order, int $count = 5): array {
    $dealt    = array_splice($deck_order, 0, $count);
    return [$dealt, $deck_order];
}

/**
 * Creates the next round for a game. Advances target/FD indexes, deals 5 cards,
 * inserts round row, updates games row. Returns false if deck is empty.
 */
function create_next_round(PDO $db, int $game_id): bool {
    $game_stmt = $db->prepare("SELECT * FROM games WHERE id = ?");
    $game_stmt->execute([$game_id]);
    $game = $game_stmt->fetch();

    $lobby_id = (int)$game['lobby_id'];
    $active_players = get_active_players($lobby_id);
    $count = count($active_players);
    if ($count === 0) return false;

    $target_idx = (int)$game['target_player_index'];
    $fd_idx     = (int)$game['final_decider_index'];

    // Advance target player (no skip constraint)
    $new_target_idx = next_active_player_index($active_players, $target_idx, -1);
    $new_target = $active_players[$new_target_idx];

    // Advance FD, skipping the new target
    $new_fd_idx = next_active_player_index($active_players, $fd_idx, (int)$new_target['turn_order']);
    $new_fd = $active_players[$new_fd_idx];

    $deck_order = json_decode($game['deck_order'], true);
    if (count($deck_order) < 5) {
        return false; // deck exhausted
    }

    [$dealt, $remaining] = deal_cards($deck_order, 5);
    $new_round_number = (int)$game['current_round'] + 1;

    $db->prepare(
        "INSERT INTO rounds (game_id, round_number, target_player_id, final_decider_id, card_ids, status, ranking_deadline)
         VALUES (?, ?, ?, ?, ?, 'ranking', DATE_ADD(NOW(), INTERVAL 60 SECOND))"
    )->execute([
        $game_id,
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
        $game_id,
    ]);

    return true;
}

/**
 * Skips the current round (e.g. due to timeout).
 * Returns 5 card_ids to bottom of deck, inserts system chat, creates next round, bumps version.
 */
function skip_round(PDO $db, int $game_id, int $round_id, string $skipped_player_name): void {
    // Get round card_ids to return to deck
    $r_stmt = $db->prepare("SELECT card_ids FROM rounds WHERE id = ?");
    $r_stmt->execute([$round_id]);
    $round = $r_stmt->fetch();
    $returned_cards = json_decode($round['card_ids'], true);

    // Mark round skipped
    $db->prepare("UPDATE rounds SET status = 'skipped' WHERE id = ?")->execute([$round_id]);

    // Append cards back to bottom of deck
    $g_stmt = $db->prepare("SELECT deck_order, lobby_id FROM games WHERE id = ?");
    $g_stmt->execute([$game_id]);
    $game = $g_stmt->fetch();
    $deck = json_decode($game['deck_order'], true);
    $deck = array_merge($deck, $returned_cards);
    $db->prepare("UPDATE games SET deck_order = ? WHERE id = ?")->execute([json_encode($deck), $game_id]);

    $lobby_id = (int)$game['lobby_id'];
    insert_system_chat($db, $lobby_id, "{$skipped_player_name}'s ranking timed out — round skipped");

    $created = create_next_round($db, $game_id);
    if (!$created) {
        $db->prepare("UPDATE games SET status = 'draw' WHERE id = ?")->execute([$game_id]);
        insert_system_chat($db, $lobby_id, "The deck is empty — it's a draw!");
    } else {
        // Announce new round
        $g2 = $db->prepare("SELECT current_round, target_player_index FROM games WHERE id = ?");
        $g2->execute([$game_id]);
        $game2 = $g2->fetch();
        $active_players = get_active_players($lobby_id);
        $target = $active_players[(int)$game2['target_player_index']] ?? null;
        $target_name = $target ? $target['name'] : 'Unknown';
        insert_system_chat($db, $lobby_id, "Round {$game2['current_round']} started! {$target_name} is ranking their cards…");
    }

    bump_version($db, $game_id);
}
