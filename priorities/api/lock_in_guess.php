<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/db_access.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/game_logic.php';

use Priorities\Models\Game;
use Priorities\Models\LetterMap;
use Priorities\Models\Round;
use Priorities\Models\ScoreResult;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$db     = get_db();
$player = require_player($db);

// Load current guessing round.
$row = dbx_fetch_round_by_status_for_lobby($db, $player->lobbyId, 'guessing');

if ($row === false) {
    http_response_code(400);
    echo json_encode(['error' => 'No active guessing round']);
    exit;
}

$round = new Round(
    id:              (int) $row['id'],
    gameId:          (int) $row['game_id'],
    roundNumber:     (int) $row['round_number'],
    targetPlayerId:  (int) $row['target_player_id'],
    finalDeciderId:  (int) $row['final_decider_id'],
    cardIds:         json_decode($row['card_ids'], true),
    targetRanking:   json_decode($row['target_ranking'], true),
    groupRanking:    $row['group_ranking'] !== null ? json_decode($row['group_ranking'], true) : null,
    result:          null,
    status:          $row['status'],
    rankingDeadline: null,
);

require_is_final_decider($player, $round);

// If no explicit group ranking has been set yet, fall back to the dealt order.
$effectiveGroupRanking = $round->groupRanking ?? $round->cardIds;
if ($round->groupRanking === null) {
    // Persist the fallback so score_round sees it.
    dbx_update_round_group_ranking($db, $round->id, json_encode($effectiveGroupRanking));
    $round = new Round(
        id:              $round->id,
        gameId:          $round->gameId,
        roundNumber:     $round->roundNumber,
        targetPlayerId:  $round->targetPlayerId,
        finalDeciderId:  $round->finalDeciderId,
        cardIds:         $round->cardIds,
        targetRanking:   $round->targetRanking,
        groupRanking:    $effectiveGroupRanking,
        result:          null,
        status:          $round->status,
        rankingDeadline: null,
    );
}

// Load game.
$game_row = dbx_fetch_game_by_id($db, $round->gameId);

$pl_map = json_decode($game_row['player_letters'], true);
$gl_map = json_decode($game_row['game_letters'], true);
$game   = new Game(
    id:                (int) $game_row['id'],
    lobbyId:           (int) $game_row['lobby_id'],
    currentRound:      (int) $game_row['current_round'],
    targetPlayerIndex: (int) $game_row['target_player_index'],
    finalDeciderIndex: (int) $game_row['final_decider_index'],
    status:            $game_row['status'],
    playerLetters:     new LetterMap(...$pl_map),
    gameLetters:       new LetterMap(...$gl_map),
    deckOrder:         json_decode($game_row['deck_order'], true),
    stateVersion:      (int) $game_row['state_version'],
    createdAt:         $game_row['created_at'],
);

$db->beginTransaction();
try {
    // Score the round.
    /** @var ScoreResult[] $results */
    $results = score_round($round);

    $player_won_ids = [];
    $game_won_ids   = [];
    foreach ($results as $r) {
        if ($r->correct) {
            $player_won_ids[] = $r->cardId;
        } else {
            $game_won_ids[] = $r->cardId;
        }
    }

    $new_player_letters = award_letters($game->playerLetters, $player_won_ids, $db);
    $new_game_letters   = award_letters($game->gameLetters, $game_won_ids, $db);

    $result_json = json_encode(array_map(
        fn(ScoreResult $r) => ['card_id' => $r->cardId, 'correct' => $r->correct],
        $results
    ));

    // Update round.
    dbx_update_round_result_revealed($db, $round->id, $result_json);

    // Check win conditions.
    $players_win = check_win($new_player_letters);
    $game_wins   = check_win($new_game_letters);

    $new_status = 'active';
    if ($players_win) {
        $new_status = 'players_win';
    } elseif ($game_wins) {
        $new_status = 'game_wins';
    }

    // Update game with new letter counts and possibly new status.
    dbx_update_game_letters_and_status(
        $db,
        $game->id,
        json_encode($new_player_letters->toArray()),
        json_encode($new_game_letters->toArray()),
        $new_status
    );

    $correct_count = count($player_won_ids);
    $total         = count($results);
    $summary       = "Round {$round->roundNumber} complete! {$correct_count}/{$total} correct.";

    if ($new_status !== 'active') {
        $win_msg = match($new_status) {
            'players_win' => 'Players win! 🎉',
            'game_wins'   => 'The game wins! 😱',
            default       => 'Draw!',
        };
        insert_system_chat($db, $game->lobbyId, $summary . ' ' . $win_msg);
    } else {
        insert_system_chat($db, $game->lobbyId, $summary);
    }

    bump_version($db, $game->id);
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}

echo json_encode(['success' => true]);
