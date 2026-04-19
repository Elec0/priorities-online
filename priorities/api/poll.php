<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/game_logic.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $player        = validate_token();
    $lobby_id      = isset($_GET['lobby_id']) ? (int)$_GET['lobby_id'] : 0;
    $state_version = isset($_GET['state_version']) ? (int)$_GET['state_version'] : 0;

    if (!$lobby_id) {
        http_response_code(400);
        echo json_encode(['error' => 'lobby_id is required']);
        exit;
    }

    if ((int)$player['lobby_id'] !== $lobby_id) {
        http_response_code(403);
        echo json_encode(['error' => 'You do not belong to this lobby']);
        exit;
    }

    $db = get_db();

    // Cleanup stale lobbies
    $db->exec("DELETE FROM lobbies WHERE updated_at < NOW() - INTERVAL 24 HOUR AND status != 'playing'");

    $l_stmt = $db->prepare("SELECT * FROM lobbies WHERE id = ?");
    $l_stmt->execute([$lobby_id]);
    $lobby = $l_stmt->fetch();
    if (!$lobby) {
        http_response_code(404);
        echo json_encode(['error' => 'Lobby not found']);
        exit;
    }

    // Fetch game if exists
    $g_stmt = $db->prepare("SELECT * FROM games WHERE lobby_id = ?");
    $g_stmt->execute([$lobby_id]);
    $game = $g_stmt->fetch();

    // Timeout check
    if ($game && $game['status'] === 'active') {
        $r_stmt = $db->prepare(
            "SELECT * FROM rounds WHERE game_id = ? AND status = 'ranking'
             AND ranking_deadline IS NOT NULL AND ranking_deadline < NOW()
             ORDER BY round_number DESC LIMIT 1"
        );
        $r_stmt->execute([$game['id']]);
        $timed_out_round = $r_stmt->fetch();
        if ($timed_out_round) {
            // Get target player name
            $tp_stmt = $db->prepare("SELECT name FROM players WHERE id = ?");
            $tp_stmt->execute([$timed_out_round['target_player_id']]);
            $tp = $tp_stmt->fetch();
            $tp_name = $tp ? $tp['name'] : 'Unknown';
            skip_round($db, (int)$game['id'], (int)$timed_out_round['id'], $tp_name);
            // Re-fetch game after skip
            $g_stmt->execute([$lobby_id]);
            $game = $g_stmt->fetch();
        }
    }

    // Build response
    if (!$game || $lobby['status'] === 'waiting') {
        // Waiting room — fetch all players
        $p_stmt = $db->prepare(
            "SELECT id, name, is_host, status FROM players WHERE lobby_id = ? ORDER BY turn_order ASC"
        );
        $p_stmt->execute([$lobby_id]);
        $players = $p_stmt->fetchAll();

        $chat = fetch_chat($db, $lobby_id);

        echo json_encode([
            'state_version' => (int)strtotime($lobby['updated_at']),
            'lobby_status'  => 'waiting',
            'lobby_code'    => $lobby['code'],
            'game_id'       => null,
            'players'       => $players,
            'chat'          => $chat,
        ]);
        exit;
    }

    // Playing / finished
    $round = get_current_round((int)$game['id']);

    // Fetch card details
    $card_ids = json_decode($round['card_ids'], true);
    $placeholders = implode(',', array_fill(0, count($card_ids), '?'));
    $c_stmt = $db->prepare("SELECT id, content, emoji, letter FROM cards WHERE id IN ($placeholders)");
    $c_stmt->execute($card_ids);
    $cards_raw = $c_stmt->fetchAll();
    // Index by id
    $cards_by_id = [];
    foreach ($cards_raw as $c) {
        $cards_by_id[(int)$c['id']] = $c;
    }
    // Build ordered cards array
    $cards_ordered = array_map(fn($id) => $cards_by_id[$id], $card_ids);

    // Fetch target and FD player info
    $tp_stmt = $db->prepare("SELECT id, name FROM players WHERE id = ?");
    $tp_stmt->execute([$round['target_player_id']]);
    $target_player = $tp_stmt->fetch();

    $fd_stmt = $db->prepare("SELECT id, name FROM players WHERE id = ?");
    $fd_stmt->execute([$round['final_decider_id']]);
    $final_decider = $fd_stmt->fetch();

    // All players
    $p_stmt = $db->prepare(
        "SELECT id, name, is_host, status FROM players WHERE lobby_id = ? ORDER BY turn_order ASC"
    );
    $p_stmt->execute([$lobby_id]);
    $players = $p_stmt->fetchAll();

    $chat = fetch_chat($db, $lobby_id);

    $round_data = [
        'id'              => (int)$round['id'],
        'number'          => (int)$round['round_number'],
        'status'          => $round['status'],
        'card_ids'        => $card_ids,
        'cards'           => $cards_ordered,
        'group_ranking'   => $round['group_ranking']  ? json_decode($round['group_ranking'],  true) : null,
        'result'          => $round['result']          ? json_decode($round['result'],          true) : null,
        'ranking_deadline'=> $round['ranking_deadline'],
    ];

    // Only include target_ranking when revealed
    if ($round['status'] === 'revealed') {
        $round_data['target_ranking'] = $round['target_ranking'] ? json_decode($round['target_ranking'], true) : null;
    }

    echo json_encode([
        'state_version'  => (int)$game['state_version'],
        'lobby_status'   => $lobby['status'],
        'lobby_code'     => $lobby['code'],
        'game_id'        => (int)$game['id'],
        'game_status'    => $game['status'],
        'round'          => $round_data,
        'target_player'  => $target_player,
        'final_decider'  => $final_decider,
        'players'        => $players,
        'player_letters' => json_decode($game['player_letters'], true),
        'game_letters'   => json_decode($game['game_letters'],   true),
        'chat'           => $chat,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

function fetch_chat(PDO $db, int $lobby_id): array {
    $stmt = $db->prepare(
        "SELECT cm.message, cm.created_at,
                COALESCE(p.name, 'System') AS player_name
         FROM chat_messages cm
         LEFT JOIN players p ON p.id = cm.player_id
         WHERE cm.lobby_id = ?
         ORDER BY cm.created_at DESC
         LIMIT 50"
    );
    $stmt->execute([$lobby_id]);
    $rows = $stmt->fetchAll();
    return array_reverse($rows);
}
