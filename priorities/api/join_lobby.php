<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/db_access.php';
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

$lobby = dbx_find_waiting_lobby_by_code($db, $code);

if ($lobby === false) {
    http_response_code(404);
    echo json_encode(['error' => 'Lobby not found or already started']);
    exit;
}
$lobby_id = (int) $lobby['id'];

// Check name uniqueness in lobby.
if (dbx_count_active_players_with_name($db, $lobby_id, $name) > 0) {
    http_response_code(409);
    echo json_encode(['error' => 'Name already taken in this lobby']);
    exit;
}

// Determine next turn_order.
$turn_order = dbx_next_turn_order($db, $lobby_id);

$token = bin2hex(random_bytes(32));

$player_id = dbx_insert_player($db, $lobby_id, $name, $token, false, $turn_order);

set_token_cookie($token);

$dev_profile = get_dev_profile();
$query       = $dev_profile !== '' ? "?lobby_id={$lobby_id}&dev_profile={$dev_profile}" : "?lobby_id={$lobby_id}";

echo json_encode([
    'success'      => true,
    'lobby_id'     => $lobby_id,
    'player_id'    => $player_id,
    'redirect_url' => "lobby.php{$query}",
]);
