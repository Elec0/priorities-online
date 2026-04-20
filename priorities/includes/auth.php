<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';

use Priorities\Models\Player;
use Priorities\Models\Round;

/** Look up the player by the current auth cookie. Returns null if missing/invalid/kicked. */
function validate_token(PDO $db): ?Player
{
    $token = get_token();
    if ($token === null) {
        return null;
    }

    $stmt = $db->prepare(
        'SELECT id, lobby_id, name, session_token, is_host, turn_order, status, joined_at
         FROM players
         WHERE session_token = :token AND status = \'active\'
         LIMIT 1'
    );
    $stmt->execute([':token' => $token]);
    $row = $stmt->fetch();

    if ($row === false) {
        return null;
    }

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

/**
 * Require a valid session token; exit with 401 JSON if missing.
 * @return Player
 */
function require_player(PDO $db): Player
{
    $player = validate_token($db);
    if ($player === null) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    return $player;
}

/** Require the player to be the lobby host; exit with 403 otherwise. */
function require_host(PDO $db): Player
{
    $player = require_player($db);
    if (!$player->isHost) {
        http_response_code(403);
        echo json_encode(['error' => 'Host only']);
        exit;
    }
    return $player;
}

/** Require the player to be the round's target player; exit with 403 otherwise. */
function require_is_target(Player $player, Round $round): void
{
    if ($player->id !== $round->targetPlayerId) {
        http_response_code(403);
        echo json_encode(['error' => 'Target player only']);
        exit;
    }
}

/** Require the player to be the round's final decider; exit with 403 otherwise. */
function require_is_final_decider(Player $player, Round $round): void
{
    if ($player->id !== $round->finalDeciderId) {
        http_response_code(403);
        echo json_encode(['error' => 'Final decider only']);
        exit;
    }
}
<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';

/**
 * Validates the session token from the priorities_token cookie.
 * Returns the player row array, or sends a 401 JSON response and exits.
 */
function validate_token(): array {
    $token = get_session_token();
    if ($token === null) {
        http_response_code(401);
        echo json_encode(['error' => 'No session token']);
        exit;
    }

    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM players WHERE session_token = ? AND status = 'active'");
    $stmt->execute([$token]);
    $player = $stmt->fetch();
    if (!$player) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid or expired session']);
        exit;
    }
    return $player;
}

/**
 * Requires the authenticated player to be the host of their lobby.
 * Returns the player row, or sends a 403 and exits.
 */
function require_host(): array {
    $player = validate_token();
    if (!$player['is_host']) {
        http_response_code(403);
        echo json_encode(['error' => 'Only the host can do this']);
        exit;
    }
    return $player;
}

/**
 * Returns the most recent non-skipped round for the given game_id.
 */
function get_current_round(int $game_id): array {
    $db = get_db();
    $stmt = $db->prepare(
        "SELECT * FROM rounds WHERE game_id = ? AND status != 'skipped' ORDER BY round_number DESC LIMIT 1"
    );
    $stmt->execute([$game_id]);
    $round = $stmt->fetch();
    if (!$round) {
        http_response_code(500);
        echo json_encode(['error' => 'No active round found']);
        exit;
    }
    return $round;
}

/**
 * Requires the authenticated player to be the Target Player for the current round.
 * Returns the player row, or sends 403 and exits.
 */
function require_is_target(int $game_id): array {
    $player = validate_token();
    $round  = get_current_round($game_id);
    if ((int)$player['id'] !== (int)$round['target_player_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'You are not the Target Player']);
        exit;
    }
    return $player;
}

/**
 * Requires the authenticated player to be the Final Decider for the current round.
 * Returns the player row, or sends 403 and exits.
 */
function require_is_final_decider(int $game_id): array {
    $player = validate_token();
    $round  = get_current_round($game_id);
    if ((int)$player['id'] !== (int)$round['final_decider_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'You are not the Final Decider']);
        exit;
    }
    return $player;
}
