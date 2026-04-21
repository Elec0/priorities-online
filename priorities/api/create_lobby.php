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
if ($name === '' || mb_strlen($name) > 50) {
    http_response_code(400);
    echo json_encode(['error' => 'Name must be 1–50 characters']);
    exit;
}

$db = get_db();

// Generate a unique 6-char uppercase lobby code.
do {
    $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    $stmt = $db->prepare("SELECT COUNT(*) FROM lobbies WHERE code = :code AND status != 'finished'");
    $stmt->execute([':code' => $code]);
} while ((int) $stmt->fetchColumn() > 0);

$token = bin2hex(random_bytes(32));

$db->beginTransaction();
try {
    $stmt = $db->prepare(
        "INSERT INTO lobbies (code, host_token, status) VALUES (:code, :token, 'waiting')"
    );
    $stmt->execute([':code' => $code, ':token' => $token]);
    $lobby_id = (int) $db->lastInsertId();

    $stmt2 = $db->prepare(
        "INSERT INTO players (lobby_id, name, session_token, is_host, turn_order)
         VALUES (:lobby_id, :name, :token, 1, 0)"
    );
    $stmt2->execute([
        ':lobby_id' => $lobby_id,
        ':name'     => $name,
        ':token'    => $token,
    ]);
    $player_id = (int) $db->lastInsertId();

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
    'redirect_url' => "/priorities/lobby.php{$query}",
]);
