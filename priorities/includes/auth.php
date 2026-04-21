<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../../vendor/autoload.php';

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
