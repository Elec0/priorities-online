<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$db     = get_db();
$player = require_player($db);

// APCu rate limit: 5 messages per token per 10 seconds.
if (function_exists('apcu_fetch')) {
    $rate_key = 'rate:send_message:' . $player->sessionToken;
    $count    = (int) apcu_fetch($rate_key);
    if ($count >= 5) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many requests']);
        exit;
    }
    apcu_store($rate_key, $count + 1, 10);
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body) || !isset($body['message']) || !is_string($body['message'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request body']);
    exit;
}

$message = trim($body['message']);
if ($message === '' || mb_strlen($message) > 256) {
    http_response_code(400);
    echo json_encode(['error' => 'Message must be 1–256 characters']);
    exit;
}

$stmt = $db->prepare(
    'INSERT INTO chat_messages (lobby_id, player_id, message) VALUES (:lobby_id, :player_id, :message)'
);
$stmt->execute([
    ':lobby_id'  => $player->lobbyId,
    ':player_id' => $player->id,
    ':message'   => $message,
]);

// Bump state version if game is active so chat appears in SSE push.
$stmt2 = $db->prepare("SELECT id FROM games WHERE lobby_id = :id LIMIT 1");
$stmt2->execute([':id' => $player->lobbyId]);
$game_row = $stmt2->fetch();
if ($game_row !== false) {
    require_once __DIR__ . '/../includes/game_logic.php';
    bump_version($db, (int) $game_row['id']);
}

echo json_encode(['success' => true]);
