<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/db_access.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/game_logic.php';

use Priorities\Models\Card;
use Priorities\Models\Game;
use Priorities\Models\LetterMap;
use Priorities\Models\Player;
use Priorities\Models\Round;

/**
 * Build the full state payload for the given lobby.
 * Returns the JSON-encodable array.
 */
function build_state_payload(int $lobby_id, PDO $db): array
{
    // Lobby
    $lobby_row = dbx_fetch_lobby_by_id($db, $lobby_id);

    if ($lobby_row === false) {
        return ['error' => 'Lobby not found'];
    }

    $players = get_active_players($lobby_id, $db);

    // Chat (last 50, ascending)
    $chat = dbx_fetch_recent_chat($db, $lobby_id);

    if ($lobby_row['status'] === 'waiting') {
        $state_ver = dbx_fetch_latest_state_version_by_lobby($db, $lobby_id);

        return [
            'state_version' => $state_ver,
            'lobby_status'  => 'waiting',
            'lobby_code'    => $lobby_row['code'],
            'game_id'       => null,
            'players'       => array_map(fn(Player $p) => player_to_array($p), $players),
            'chat'          => $chat,
        ];
    }

    // Playing state
    $game_row = dbx_fetch_latest_game_by_lobby($db, $lobby_id);

    $game = hydrate_game($game_row);

    // Prioritise active rounds (ranking/guessing) over revealed so the revealed screen
    // stays visible until next_round.php is called, then the new ranking round takes over.
        $round_row = dbx_fetch_prioritized_round_for_game($db, $game->id);
    $round     = hydrate_round($round_row);

    // Cards for this round
        $card_rows = dbx_fetch_cards_by_ids($db, $round->cardIds);
    $cards_by_id = [];
        foreach ($card_rows as $c) {
        $cards_by_id[(int) $c['id']] = $c;
    }
    $cards = array_map(fn(int $id) => $cards_by_id[$id], $round->cardIds);

    // Resolve target and FD player objects
    $players_map = [];
    foreach ($players as $p) {
        $players_map[$p->id] = $p;
    }

    // Also fetch kicked players for target/FD resolution if needed
    $target_player = $players_map[$round->targetPlayerId] ?? fetch_player_by_id($round->targetPlayerId, $db);
    $fd_player     = $players_map[$round->finalDeciderId] ?? fetch_player_by_id($round->finalDeciderId, $db);

    $round_payload = [
        'id'               => $round->id,
        'number'           => $round->roundNumber,
        'status'           => $round->status,
        'card_ids'         => $round->cardIds,
        'cards'            => array_values(array_map(fn(array $c) => [
            'id'       => (int) $c['id'],
            'content'  => $c['content'],
            'category' => $c['category'],
            'emoji'    => $c['emoji'],
            'letter'   => $c['letter'],
        ], $cards)),
        'group_ranking'    => $round->groupRanking,
        'ranking_deadline' => $round->rankingDeadline,
    ];

    // Only expose target_ranking after reveal to prevent cheating.
    if ($round->status === 'revealed') {
        $round_payload['target_ranking'] = $round->targetRanking;
        $round_payload['result']         = $round->result;
    }

    return [
        'state_version'  => $game->stateVersion,
        'lobby_status'   => 'playing',
        'lobby_code'     => $lobby_row['code'],
        'game_id'        => $game->id,
        'game_status'    => $game->status,
        'round'          => $round_payload,
        'target_player'  => player_to_array($target_player),
        'final_decider'  => player_to_array($fd_player),
        'players'        => array_map(fn(Player $p) => player_to_array($p), $players),
        'player_letters' => $game->playerLetters->toArray(),
        'game_letters'   => $game->gameLetters->toArray(),
        'chat'           => $chat,
    ];
}

function player_to_array(Player $p): array
{
    return [
        'id'         => $p->id,
        'name'       => $p->name,
        'turn_order' => $p->turnOrder,
        'is_host'    => $p->isHost,
        'status'     => $p->status,
    ];
}

function fetch_player_by_id(int $id, PDO $db): Player
{
    $row = dbx_fetch_player_by_id($db, $id);
    return new Player(
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

// ── SSE stream ────────────────────────────────────────────────────────────────

$db = get_db();

$lobby_id   = (int) ($_GET['lobby_id'] ?? 0);
$client_ver = (int) ($_GET['state_version'] ?? 0);

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
header('Connection: keep-alive');

// Flush output buffers so headers go immediately.
if (ob_get_level()) ob_end_flush();

$player = validate_token($db);
if ($player === null || $player->lobbyId !== $lobby_id) {
    echo "data: {\"state_version\":0,\"lobby_status\":\"unauthorized\"}\n\n";
    flush();
    exit;
}

// Stale lobby cleanup (piggyback on connection).
dbx_delete_stale_lobbies($db);

$hold_duration = 30;
$poll_interval = 300_000; // 300ms in microseconds
$start         = time();
$first_iter    = true;

while ((time() - $start) < $hold_duration) {
    // Check for timed-out ranking rounds.
    $timed_out = dbx_fetch_timed_out_ranking_round_for_lobby($db, $lobby_id);

    if ($timed_out !== false) {
        $game_row = dbx_fetch_latest_game_by_lobby($db, $lobby_id);

        if ($game_row !== false) {
            // Atomically claim the round by flipping it out of 'ranking'.
            // Only the first stream to win this UPDATE will proceed; others get 0 rows.
            if (dbx_claim_round_skip($db, (int) $timed_out['id']) === 1) {
                $game  = hydrate_game($game_row);
                $round = hydrate_round($timed_out);

                $target_player = fetch_player_by_id($round->targetPlayerId, $db);

                // skip_round does UPDATE rounds SET status='skipped' itself,
                // but we already did it above — call the rest of the work directly.
                $new_deck = array_merge($game->deckOrder, $round->cardIds);

                     dbx_update_game_deck($db, $game->id, json_encode($new_deck));

                insert_system_chat(
                    $db,
                    $lobby_id,
                    "{$target_player->name} ran out of time! Round skipped."
                );

                $updated_game = new Game(
                    id:                $game->id,
                    lobbyId:           $game->lobbyId,
                    currentRound:      $game->currentRound,
                    targetPlayerIndex: $game->targetPlayerIndex,
                    finalDeciderIndex: $game->finalDeciderIndex,
                    status:            $game->status,
                    playerLetters:     $game->playerLetters,
                    gameLetters:       $game->gameLetters,
                    deckOrder:         $new_deck,
                    stateVersion:      $game->stateVersion,
                    createdAt:         $game->createdAt,
                );

                if (!create_next_round($db, $updated_game)) {
                    dbx_set_game_draw($db, $game->id);
                    insert_system_chat($db, $lobby_id, 'The deck ran out! The game is a draw.');
                }

                bump_version($db, $game->id);
            }
        }
    }

    // Check version.
    $server_ver = dbx_fetch_latest_state_version_by_lobby($db, $lobby_id);

    if ($first_iter || $server_ver !== $client_ver) {
        $first_iter = false;
        // Only cache playing-lobby state; waiting-lobby state changes on
        // every join/leave but the version stays 0, so we must not cache it.
        $cache_key = $server_ver > 0 ? "game:lobby:{$lobby_id}:{$server_ver}" : null;
        $payload   = false;
        if ($cache_key !== null && function_exists('apcu_fetch')) {
            $payload = apcu_fetch($cache_key);
        }
        if ($payload === false) {
            $payload = json_encode(build_state_payload($lobby_id, $db));
            if ($cache_key !== null && function_exists('apcu_store')) {
                apcu_store($cache_key, $payload, 60);
            }
        }

        echo "data: {$payload}\n\n";
        flush();
        exit;
    }

    usleep($poll_interval);

    // Abort if client disconnected.
    if (connection_aborted()) {
        exit;
    }
}

// Keepalive — EventSource will reconnect.
echo ": keepalive\n\n";
flush();
