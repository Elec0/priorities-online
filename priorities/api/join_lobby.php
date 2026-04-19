<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$code = strtoupper(trim($_POST['code'] ?? ''));

if ($name === '' || mb_strlen($name) > 50) {
    http_response_code(400);
    echo json_encode(['error' => 'Name is required and must be 50 characters or fewer']);
    exit;
}
if (strlen($code) !== 6) {
    http_response_code(400);
    echo json_encode(['error' => 'Lobby code must be 6 characters']);
    exit;
}

try {
    $db = get_db();

    $stmt = $db->prepare("SELECT * FROM lobbies WHERE UPPER(code) = ? AND status = 'waiting'");
    $stmt->execute([$code]);
    $lobby = $stmt->fetch();
    if (!$lobby) {
        http_response_code(404);
        echo json_encode(['error' => 'Lobby not found or game already started']);
        exit;
    }

    // Count active players
    $cnt_stmt = $db->prepare("SELECT COUNT(*) FROM players WHERE lobby_id = ? AND status = 'active'");
    $cnt_stmt->execute([$lobby['id']]);
    if ((int)$cnt_stmt->fetchColumn() >= 6) {
        http_response_code(400);
        echo json_encode(['error' => 'Lobby is full (max 6 players)']);
        exit;
    }

    // Check name not taken
    $name_stmt = $db->prepare("SELECT id FROM players WHERE lobby_id = ? AND status = 'active' AND LOWER(name) = LOWER(?)");
    $name_stmt->execute([$lobby['id'], $name]);
    if ($name_stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'That name is already taken in this lobby']);
        exit;
    }

    // Next turn_order
    $order_stmt = $db->prepare("SELECT COALESCE(MAX(turn_order), -1) + 1 FROM players WHERE lobby_id = ?");
    $order_stmt->execute([$lobby['id']]);
    $turn_order = (int)$order_stmt->fetchColumn();

    $token = bin2hex(random_bytes(32));

    $db->prepare(
        "INSERT INTO players (lobby_id, name, session_token, is_host, turn_order, status)
         VALUES (?, ?, ?, 0, ?, 'active')"
    )->execute([$lobby['id'], $name, $token, $turn_order]);
    $player_id = (int)$db->lastInsertId();

    $db->prepare("UPDATE lobbies SET updated_at = NOW() WHERE id = ?")->execute([$lobby['id']]);

    setcookie('priorities_token', $token, [
        'expires'  => time() + 86400 * 7,
        'httponly' => true,
        'samesite' => 'Strict',
        'path'     => '/',
    ]);

    echo json_encode([
        'success'   => true,
        'lobby_id'  => (int)$lobby['id'],
        'player_id' => $player_id,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
