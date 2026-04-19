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
    $player = validate_token();
    $db     = get_db();

    $g_stmt = $db->prepare(
        "SELECT g.* FROM games g
         JOIN lobbies l ON l.id = g.lobby_id
         WHERE g.lobby_id = ? AND l.status = 'playing'"
    );
    $g_stmt->execute([$player['lobby_id']]);
    $game = $g_stmt->fetch();
    if (!$game) {
        http_response_code(400);
        echo json_encode(['error' => 'No active game found']);
        exit;
    }

    $round = get_current_round((int)$game['id']);
    if ($round['status'] !== 'guessing') {
        http_response_code(400);
        echo json_encode(['error' => 'Not in guessing phase']);
        exit;
    }

    if ((int)$player['id'] === (int)$round['target_player_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'The Target Player cannot update the group guess']);
        exit;
    }

    $body = json_decode(file_get_contents('php://input'), true);
    $ranking = $body['ranking'] ?? null;

    if (!is_array($ranking) || count($ranking) !== 5) {
        http_response_code(400);
        echo json_encode(['error' => 'ranking must be an array of 5 card IDs']);
        exit;
    }

    $card_ids = json_decode($round['card_ids'], true);
    $ranking  = array_map('intval', $ranking);
    $card_ids_sorted = $card_ids;
    sort($card_ids_sorted);
    $ranking_sorted = $ranking;
    sort($ranking_sorted);

    if ($card_ids_sorted !== $ranking_sorted) {
        http_response_code(400);
        echo json_encode(['error' => 'ranking must contain exactly the 5 drawn card IDs']);
        exit;
    }

    $db->prepare("UPDATE rounds SET group_ranking = ? WHERE id = ?")
       ->execute([json_encode($ranking), $round['id']]);

    bump_version($db, (int)$game['id']);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
