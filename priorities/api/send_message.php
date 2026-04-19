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

    $body    = json_decode(file_get_contents('php://input'), true);
    $message = trim($body['message'] ?? '');
    $message = strip_tags($message);

    if ($message === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Message cannot be empty']);
        exit;
    }
    if (mb_strlen($message) > 500) {
        $message = mb_substr($message, 0, 500);
    }

    $db->prepare("INSERT INTO chat_messages (lobby_id, player_id, message) VALUES (?, ?, ?)")
       ->execute([$player['lobby_id'], $player['id'], $message]);

    // Bump version if game exists
    $g_stmt = $db->prepare("SELECT id FROM games WHERE lobby_id = ?");
    $g_stmt->execute([$player['lobby_id']]);
    $game = $g_stmt->fetch();
    if ($game) {
        bump_version($db, (int)$game['id']);
    } else {
        $db->prepare("UPDATE lobbies SET updated_at = NOW() WHERE id = ?")->execute([$player['lobby_id']]);
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
