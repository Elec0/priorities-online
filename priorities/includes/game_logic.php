<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Priorities\Models\Card;
use Priorities\Models\Game;
use Priorities\Models\LetterMap;
use Priorities\Models\Player;
use Priorities\Models\Round;
use Priorities\Models\ScoreResult;

// ── Pure functions ────────────────────────────────────────────────────────────

/** Return a LetterMap with all counts at zero. */
function empty_letters(): LetterMap
{
    return new LetterMap(P: 0, R: 0, I: 0, O: 0, T: 0, E: 0, S: 0);
}

/** Return true iff the letter map satisfies the win condition. */
function check_win(LetterMap $letters): bool
{
    return $letters->checkWin();
}

/**
 * Compare target_ranking vs group_ranking position-by-position.
 * @return ScoreResult[]
 */
function score_round(Round $round): array
{
    $results = [];
    for ($i = 0; $i < 5; $i++) {
        $targetCardId = (int) $round->targetRanking[$i];
        $groupCardId  = (int) $round->groupRanking[$i];
        $results[]    = new ScoreResult(
            cardId:  $targetCardId,
            correct: $targetCardId === $groupCardId,
        );
    }
    return $results;
}

/**
 * Splice $count items from the front of $deck_order.
 * @param int[] $deck_order
 * @return array{0: int[], 1: int[]} [$dealt, $remaining]
 */
function deal_cards(array $deck_order, int $count = 5): array
{
    $dealt     = array_slice($deck_order, 0, $count);
    $remaining = array_slice($deck_order, $count);
    return [$dealt, $remaining];
}

/**
 * Return the next active player index with wrap-around.
 * Optionally skips any player whose turn_order equals $skip_turn_order.
 *
 * @param Player[] $active_players  Sorted by turn_order ASC.
 * @param int      $current_index
 * @param int      $skip_turn_order  -1 means no skip.
 */
function next_active_player_index(
    array $active_players,
    int $current_index,
    int $skip_turn_order = -1
): int {
    $count = count($active_players);
    if ($count === 0) {
        return 0;
    }

    for ($i = 1; $i <= $count; $i++) {
        $next = ($current_index + $i) % $count;
        if ($skip_turn_order === -1) {
            return $next;
        }
        if ($active_players[$next]->turnOrder !== $skip_turn_order) {
            return $next;
        }
    }

    // All players have the skip turn_order — fall back to simple advance.
    return ($current_index + 1) % $count;
}

// ── DB-touching functions ─────────────────────────────────────────────────────

/**
 * @return Player[]
 */
function get_active_players(int $lobby_id, PDO $db): array
{
    $stmt = $db->prepare(
        'SELECT id, lobby_id, name, session_token, is_host, turn_order, status, joined_at
         FROM players
         WHERE lobby_id = :lobby_id AND status = \'active\'
         ORDER BY turn_order ASC'
    );
    $stmt->execute([':lobby_id' => $lobby_id]);

    $players = [];
    foreach ($stmt->fetchAll() as $row) {
        $players[] = new Player(
            id:           (int) $row['id'],
            lobbyId:      (int) $row['lobby_id'],
            name:         $row['name'],
            sessionToken: $row['session_token'],
            isHost:       (bool) $row['is_host'],
            turnOrder:    (int) $row['turn_order'],
            status:       $row['status'],
            joinedAt:     $row['joined_at'],
        );
    }
    return $players;
}

/**
 * Increment each relevant letter in the map for the given won card IDs.
 * @param int[] $won_card_ids
 */
function award_letters(LetterMap $current_letters, array $won_card_ids, PDO $db): LetterMap
{
    if (count($won_card_ids) === 0) {
        return $current_letters;
    }

    $placeholders = implode(',', array_fill(0, count($won_card_ids), '?'));
    $stmt = $db->prepare("SELECT letter FROM cards WHERE id IN ({$placeholders})");
    $stmt->execute($won_card_ids);

    $map = $current_letters;
    foreach ($stmt->fetchAll() as $row) {
        $map = $map->withIncrement($row['letter']);
    }
    return $map;
}

/** Hydrate a Game model from a DB row. */
function hydrate_game(array $row): Game
{
    $pl = json_decode($row['player_letters'], true);
    $gl = json_decode($row['game_letters'], true);
    return new Game(
        id:                (int) $row['id'],
        lobbyId:           (int) $row['lobby_id'],
        currentRound:      (int) $row['current_round'],
        targetPlayerIndex: (int) $row['target_player_index'],
        finalDeciderIndex: (int) $row['final_decider_index'],
        status:            $row['status'],
        playerLetters:     new LetterMap(...$pl),
        gameLetters:       new LetterMap(...$gl),
        deckOrder:         json_decode($row['deck_order'], true),
        stateVersion:      (int) $row['state_version'],
        createdAt:         $row['created_at'],
    );
}

/** Hydrate a Round model from a DB row. */
function hydrate_round(array $row): Round
{
    return new Round(
        id:              (int) $row['id'],
        gameId:          (int) $row['game_id'],
        roundNumber:     (int) $row['round_number'],
        targetPlayerId:  (int) $row['target_player_id'],
        finalDeciderId:  (int) $row['final_decider_id'],
        cardIds:         json_decode($row['card_ids'], true),
        targetRanking:   $row['target_ranking'] !== null ? json_decode($row['target_ranking'], true) : null,
        groupRanking:    $row['group_ranking'] !== null ? json_decode($row['group_ranking'], true) : null,
        result:          $row['result'] !== null ? json_decode($row['result'], true) : null,
        status:          $row['status'],
        rankingDeadline: $row['ranking_deadline'],
    );
}

/** Increment state_version by 1. */
function bump_version(PDO $db, int $game_id): void
{
    $stmt = $db->prepare('UPDATE games SET state_version = state_version + 1 WHERE id = :id');
    $stmt->execute([':id' => $game_id]);
}

/** Insert a system chat message (player_id = NULL). */
function insert_system_chat(PDO $db, int $lobby_id, string $message): void
{
    $stmt = $db->prepare(
        'INSERT INTO chat_messages (lobby_id, player_id, message) VALUES (:lobby_id, NULL, :message)'
    );
    $stmt->execute([':lobby_id' => $lobby_id, ':message' => $message]);
}

/**
 * Deal 5 cards, create the next round row, advance target/FD indexes.
 * Runs inside an InnoDB transaction.
 * Returns false if fewer than 5 cards remain in the deck.
 */
function create_next_round(PDO $db, Game $game): bool
{
    if (count($game->deckOrder) < 5) {
        return false;
    }

    $active_players = get_active_players($game->lobbyId, $db);

    $new_target_index = next_active_player_index(
        $active_players,
        $game->targetPlayerIndex
    );
    $new_target_turn_order = $active_players[$new_target_index]->turnOrder;

    $new_fd_index = next_active_player_index(
        $active_players,
        $game->finalDeciderIndex,
        $new_target_turn_order
    );

    [$dealt, $remaining] = deal_cards($game->deckOrder);

    $target_player = $active_players[$new_target_index];
    $fd_player     = $active_players[$new_fd_index];

    $round_number = $game->currentRound + 1;

    // Look up timer settings from the lobby.
    $timer_stmt = $db->prepare('SELECT timer_enabled, timer_seconds FROM lobbies WHERE id = :id');
    $timer_stmt->execute([':id' => $game->lobbyId]);
    $timer_row     = $timer_stmt->fetch();
    $timer_enabled = $timer_row !== false && (bool) $timer_row['timer_enabled'];
    $timer_seconds = $timer_row !== false ? max(10, (int) $timer_row['timer_seconds']) : 60;

    $deadline_sql = $timer_enabled
        ? "DATE_ADD(NOW(), INTERVAL {$timer_seconds} SECOND)"
        : 'NULL';

    $stmt = $db->prepare(
        "INSERT INTO rounds
         (game_id, round_number, target_player_id, final_decider_id, card_ids, status, ranking_deadline)
         VALUES (:game_id, :round_number, :target_id, :fd_id, :card_ids, 'ranking',
                 {$deadline_sql})"
    );
    $stmt->execute([
        ':game_id'      => $game->id,
        ':round_number' => $round_number,
        ':target_id'    => $target_player->id,
        ':fd_id'        => $fd_player->id,
        ':card_ids'     => json_encode($dealt),
    ]);

    $stmt2 = $db->prepare(
        'UPDATE games
         SET current_round        = :round_number,
             target_player_index  = :target_index,
             final_decider_index  = :fd_index,
             deck_order           = :deck_order
         WHERE id = :id'
    );
    $stmt2->execute([
        ':round_number'  => $round_number,
        ':target_index'  => $new_target_index,
        ':fd_index'      => $new_fd_index,
        ':deck_order'    => json_encode($remaining),
        ':id'            => $game->id,
    ]);

    return true;
}

/**
 * Mark the current round as skipped, return cards to bottom of deck,
 * insert system chat, and start the next round (or set draw if deck empty).
 */
function skip_round(PDO $db, Game $game, Round $round, string $skipped_player_name): void
{
    // Return cards to the bottom of the deck.
    $new_deck = array_merge($game->deckOrder, $round->cardIds);

    $stmt = $db->prepare(
        'UPDATE rounds SET status = \'skipped\' WHERE id = :id'
    );
    $stmt->execute([':id' => $round->id]);

    $stmt2 = $db->prepare(
        'UPDATE games SET deck_order = :deck WHERE id = :id'
    );
    $stmt2->execute([':deck' => json_encode($new_deck), ':id' => $game->id]);

    insert_system_chat(
        $db,
        $game->lobbyId,
        "{$skipped_player_name} ran out of time! Round skipped."
    );

    // Reload game with updated deck before creating next round.
    $updated_game = new Game(
        id:                 $game->id,
        lobbyId:            $game->lobbyId,
        currentRound:       $game->currentRound,
        targetPlayerIndex:  $game->targetPlayerIndex,
        finalDeciderIndex:  $game->finalDeciderIndex,
        status:             $game->status,
        playerLetters:      $game->playerLetters,
        gameLetters:        $game->gameLetters,
        deckOrder:          $new_deck,
        stateVersion:       $game->stateVersion,
        createdAt:          $game->createdAt,
    );

    if (!create_next_round($db, $updated_game)) {
        $stmt3 = $db->prepare("UPDATE games SET status = 'draw' WHERE id = :id");
        $stmt3->execute([':id' => $game->id]);
        insert_system_chat($db, $game->lobbyId, 'The deck ran out! The game is a draw.');
    }

    bump_version($db, $game->id);
}
