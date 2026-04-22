<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$player = validate_token(get_db());
if ($player === null) {
    header('Location: ./');
    exit;
}

$lobby_id    = (int) ($_GET['lobby_id'] ?? 0);
$dev_profile = get_dev_profile();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Priorities — Game</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div
    id="root"
    data-lobby-id="<?= htmlspecialchars((string) $lobby_id) ?>"
    data-player-id="<?= htmlspecialchars((string) $player->id) ?>"
    data-dev-profile="<?= htmlspecialchars($dev_profile) ?>"
  ></div>
  <script type="module" src="assets/js/dist/game.js"></script>
</body>
</html>
