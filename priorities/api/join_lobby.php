<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$name = trim((string) ($_POST['name'] ?? ''));
$code = strtoupper(trim((string) ($_POST['code'] ?? '')));

if ($name === '' || mb_strlen($name) > 50) {
    http_response_code(400);
    echo json_encode(['error' => 'Name must be 1–50 characters']);
    exit;
}
if (strlen($code) !== 6) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid lobby code']);
    exit;
}

$db = get_db();

$stmt = $db->prepare(
    "SELECT id FROM lobbies WHERE code = :code AND status = 'waiting' LIMIT 1"
);
$stmt->execute([':code' => $code]);
$lobby = $stmt->fetch();

if ($lobby === false) {
    http_response_code(404);
    echo json_encode(['error' => 'Lobby not found or already started']);
    exit;
}
$lobby_id = (int) $lobby['id'];

// Check name uniqueness in lobby.
$stmt2 = $db->prepare(
    "SELECT COUNT(*) FROM players WHERE lobby_id = :lobby_id AND name = :name AND status = 'active'"
);
$stmt2->execute([':lobby_id' => $lobby_id, ':name' => $name]);
if ((int) $stmt2->fetchColumn() > 0) {
    http_response_code(409);
    echo json_encode(['error' => 'Name already taken in this lobby']);
    exit;
}

// Determine next turn_order.
$stmt3 = $db->prepare(
    'SELECT COALESCE(MAX(turn_order), -1) + 1 AS next_order FROM players WHERE lobby_id = :lobby_id'
);
$stmt3->execute([':lobby_id' => $lobby_id]);
$turn_order = (int) $stmt3->fetchColumn();

$token = bin2hex(random_bytes(32));

$stmt4 = $db->prepare(
    'INSERT INTO players (lobby_id, name, session_token, is_host, turn_order)
     VALUES (:lobby_id, :name, :token, 0, :turn_order)'
);
$stmt4->execute([
    ':lobby_id'   => $lobby_id,
    ':name'       => $name,
    ':token'      => $token,
    ':turn_order' => $turn_order,
]);
$player_id = (int) $db->lastInsertId();

set_token_cookie($token);

$dev_profile = get_dev_profile();
$query       = $dev_profile !== '' ? "?lobby_id={$lobby_id}&dev_profile={$dev_profile}" : "?lobby_id={$lobby_id}";

echo json_encode([
    'success'      => true,
    'lobby_id'     => $lobby_id,
    'player_id'    => $player_id,
    'redirect_url' => "/priorities/lobby.php{$query}",
]);
