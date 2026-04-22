<?php
declare(strict_types=1);

require_once __DIR__ . '/db_access.php';

$autoload_paths = [
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
];
foreach ($autoload_paths as $autoload_path) {
    if (is_file($autoload_path)) {
        require_once $autoload_path;
        break;
    }
}

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
    $rows = dbx_fetch_active_players_by_lobby($db, $lobby_id);
    $players = [];
    foreach ($rows as $row) {
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

    $map = $current_letters;
    foreach (dbx_fetch_card_letters_by_ids($db, $won_card_ids) as $letter) {
        $map = $map->withIncrement($letter);
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
    dbx_increment_state_version($db, $game_id);
}

/** Insert a system chat message (player_id = NULL). */
function insert_system_chat(PDO $db, int $lobby_id, string $message): void
{
    dbx_insert_system_chat_message($db, $lobby_id, $message);
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
    shuffle($dealt);

    $target_player = $active_players[$new_target_index];
    $fd_player     = $active_players[$new_fd_index];

    $round_number = $game->currentRound + 1;

    // Look up timer settings from the lobby.
    $timer_settings = dbx_lobby_timer_settings($db, $game->lobbyId);

    dbx_insert_round(
        $db,
        $game->id,
        $round_number,
        $target_player->id,
        $fd_player->id,
        json_encode($dealt),
        $timer_settings['timer_enabled'],
        $timer_settings['timer_seconds']
    );

    dbx_update_game_for_next_round(
        $db,
        $game->id,
        $round_number,
        $new_target_index,
        $new_fd_index,
        json_encode($remaining)
    );

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

    dbx_mark_round_skipped($db, $round->id);
    dbx_update_game_deck($db, $game->id, json_encode($new_deck));

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
        dbx_set_game_draw($db, $game->id);
        insert_system_chat($db, $game->lobbyId, 'The deck ran out! The game is a draw.');
    }

    bump_version($db, $game->id);
}
