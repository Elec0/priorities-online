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
    $host = require_host();

    $body = json_decode(file_get_contents('php://input'), true);
    $kicked_id = isset($body['player_id']) ? (int)$body['player_id'] : 0;
    if (!$kicked_id) {
        http_response_code(400);
        echo json_encode(['error' => 'player_id is required']);
        exit;
    }

    $db = get_db();

    // Fetch kicked player
    $stmt = $db->prepare("SELECT * FROM players WHERE id = ? AND lobby_id = ?");
    $stmt->execute([$kicked_id, $host['lobby_id']]);
    $kicked = $stmt->fetch();
    if (!$kicked) {
        http_response_code(404);
        echo json_encode(['error' => 'Player not found in this lobby']);
        exit;
    }
    if ($kicked['is_host']) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot kick the host']);
        exit;
    }

    // Kick the player
    $db->prepare("UPDATE players SET status = 'kicked' WHERE id = ?")->execute([$kicked_id]);

    // Fetch lobby status
    $lobby_stmt = $db->prepare("SELECT * FROM lobbies WHERE id = ?");
    $lobby_stmt->execute([$host['lobby_id']]);
    $lobby = $lobby_stmt->fetch();

    if ($lobby['status'] === 'playing') {
        $g_stmt = $db->prepare("SELECT * FROM games WHERE lobby_id = ?");
        $g_stmt->execute([$host['lobby_id']]);
        $game = $g_stmt->fetch();
        if ($game) {
            $game_id = (int)$game['id'];
            $round = get_current_round($game_id);

            if ((int)$round['target_player_id'] === $kicked_id) {
                // Skip the round — the target was kicked
                skip_round($db, $game_id, (int)$round['id'], $kicked['name']);
            } elseif ((int)$round['final_decider_id'] === $kicked_id) {
                // Reassign FD to next active player skipping new target
                $active_players = get_active_players($host['lobby_id']);
                $target_turn_order = -1;
                foreach ($active_players as $p) {
                    if ((int)$p['id'] === (int)$round['target_player_id']) {
                        $target_turn_order = (int)$p['turn_order'];
                        break;
                    }
                }
                // Find current FD index in new active list
                $fd_idx = 0;
                foreach ($active_players as $idx => $p) {
                    if ((int)$p['id'] === $kicked_id) { $fd_idx = $idx; break; }
                }
                // Wrap back so next_active_player_index goes from that position
                $new_fd_idx = next_active_player_index($active_players, $fd_idx > 0 ? $fd_idx - 1 : count($active_players) - 1, $target_turn_order);
                $new_fd = $active_players[$new_fd_idx];

                $db->prepare("UPDATE rounds SET final_decider_id = ? WHERE id = ?")->execute([$new_fd['id'], $round['id']]);
                $db->prepare("UPDATE games SET final_decider_index = ? WHERE id = ?")->execute([$new_fd_idx, $game_id]);
                bump_version($db, $game_id);
            } else {
                bump_version($db, $game_id);
            }
        }
    } else {
        // Waiting room — touch lobby updated_at
        $db->prepare("UPDATE lobbies SET updated_at = NOW() WHERE id = ?")->execute([$host['lobby_id']]);
    }

    insert_system_chat($db, $host['lobby_id'], "{$kicked['name']} was removed from the game");

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
