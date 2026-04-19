<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$name = trim($_POST['name'] ?? '');
if ($name === '' || mb_strlen($name) > 50) {
    http_response_code(400);
    echo json_encode(['error' => 'Name is required and must be 50 characters or fewer']);
    exit;
}

try {
    $db = get_db();

    // Generate unique 6-char lobby code
    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= chr(random_int(65, 90)); // A-Z
        }
        $check = $db->prepare("SELECT id FROM lobbies WHERE code = ?");
        $check->execute([$code]);
    } while ($check->fetch());

    $token = bin2hex(random_bytes(32));

    $db->prepare("INSERT INTO lobbies (code, host_token, status) VALUES (?, ?, 'waiting')")
       ->execute([$code, $token]);
    $lobby_id = (int)$db->lastInsertId();

    $db->prepare(
        "INSERT INTO players (lobby_id, name, session_token, is_host, turn_order, status)
         VALUES (?, ?, ?, 1, 0, 'active')"
    )->execute([$lobby_id, $name, $token]);
    $player_id = (int)$db->lastInsertId();

    setcookie('priorities_token', $token, [
        'expires'  => time() + 86400 * 7,
        'httponly' => true,
        'samesite' => 'Strict',
        'path'     => '/',
    ]);

    echo json_encode([
        'success'   => true,
        'code'      => $code,
        'lobby_id'  => $lobby_id,
        'player_id' => $player_id,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
