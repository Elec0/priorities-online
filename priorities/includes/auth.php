<?php
require_once __DIR__ . '/db.php';

/**
 * Validates the session token from the priorities_token cookie.
 * Returns the player row array, or sends a 401 JSON response and exits.
 */
function validate_token(): array {
    if (empty($_COOKIE['priorities_token'])) {
        http_response_code(401);
        echo json_encode(['error' => 'No session token']);
        exit;
    }
    $token = $_COOKIE['priorities_token'];
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
