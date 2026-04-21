<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$player = validate_token(get_db());
if ($player === null) {
    header('Location: /priorities/');
    exit;
}

$lobby_id   = (int) ($_GET['lobby_id'] ?? 0);
$dev_profile = get_dev_profile();
$is_host    = $player->isHost ? '1' : '0';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Priorities — Lobby</title>
  <link rel="stylesheet" href="/priorities/assets/css/style.css">
</head>
<body>
  <div
    id="root"
    data-lobby-id="<?= htmlspecialchars((string) $lobby_id) ?>"
    data-player-id="<?= htmlspecialchars((string) $player->id) ?>"
    data-is-host="<?= htmlspecialchars($is_host) ?>"
    data-dev-profile="<?= htmlspecialchars($dev_profile) ?>"
  ></div>
  <script type="module" src="/priorities/assets/js/dist/lobby.js"></script>
</body>
</html>
