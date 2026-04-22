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
if ($name === '' || mb_strlen($name) > 50) {
    http_response_code(400);
    echo json_encode(['error' => 'Name must be 1–50 characters']);
    exit;
}

$timer_enabled = filter_var($_POST['timer_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
$timer_seconds = (int) ($_POST['timer_seconds'] ?? 60);
if ($timer_seconds < 10 || $timer_seconds > 600) {
    $timer_seconds = 60;
}

$db = get_db();

// Generate a unique 6-char uppercase lobby code.
do {
    $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
} while (dbx_lobby_code_exists($db, $code));

$token = bin2hex(random_bytes(32));

$db->beginTransaction();
try {
    $lobby_id = dbx_insert_lobby($db, $code, $token, $timer_enabled, $timer_seconds);
    $player_id = dbx_insert_player($db, $lobby_id, $name, $token, true, 0);

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}

set_token_cookie($token);

$dev_profile = get_dev_profile();
$query       = $dev_profile !== '' ? "?lobby_id={$lobby_id}&dev_profile={$dev_profile}" : "?lobby_id={$lobby_id}";

echo json_encode([
    'success'      => true,
    'code'         => $code,
    'lobby_id'     => $lobby_id,
    'player_id'    => $player_id,
    'redirect_url' => "lobby.php{$query}",
]);
