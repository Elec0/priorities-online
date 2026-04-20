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
  <title>Lobby — Priorities</title>
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
<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';

$lobby_id = isset($_GET['lobby_id']) ? (int)$_GET['lobby_id'] : 0;
if (!$lobby_id) {
    header('Location: ' . build_path('/priorities/'));
    exit;
}

// Validate token and get player
$player = null;
if (($token = get_session_token()) !== null) {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM players WHERE session_token = ? AND status = 'active' AND lobby_id = ?");
    $stmt->execute([$token, $lobby_id]);
    $player = $stmt->fetch();
}

if (!$player) {
    header('Location: ' . build_path('/priorities/'));
    exit;
}

// If already playing, redirect to game
$l_stmt = $db->prepare("SELECT status FROM lobbies WHERE id = ?");
$l_stmt->execute([$lobby_id]);
$lobby = $l_stmt->fetch();
if ($lobby && $lobby['status'] === 'playing') {
    header('Location: ' . build_path('/priorities/game.php', ['lobby_id' => $lobby_id]));
    exit;
}

$dev_profile = get_dev_profile();
$home_url = build_path('/priorities/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Priorities — Lobby</title>
    <link rel="stylesheet" href="/priorities/assets/css/style.css">
</head>
<body class="lobby-page"
      data-lobby-id="<?= $lobby_id ?>"
      data-player-id="<?= (int)$player['id'] ?>"
      data-is-host="<?= (int)$player['is_host'] ?>"
      data-dev-profile="<?= htmlspecialchars($dev_profile, ENT_QUOTES) ?>">

    <div class="lobby-container">
        <header class="lobby-header">
            <h1>PRIORITIES</h1>
            <div class="lobby-code-display">
                <span class="lobby-code-label">Lobby Code</span>
                <span class="lobby-code" id="lobby-code">Loading…</span>
                <button class="btn-copy" onclick="copyCode()" title="Copy code">📋</button>
            </div>
        </header>

        <div class="lobby-body">
            <section class="lobby-players">
                <h2>Players <span id="player-count">(0)</span></h2>
                <ul id="player-list" class="player-list"></ul>
            </section>

            <section class="lobby-actions">
                <div id="start-hint" class="start-hint">Need at least 3 players to start</div>
                <?php if ($player['is_host']): ?>
                <button id="start-btn" class="btn btn-primary btn-large" disabled>Start Game</button>
                <?php else: ?>
                <p class="waiting-msg">Waiting for the host to start the game…</p>
                <?php endif; ?>
            </section>

            <section class="lobby-chat">
                <h2>Chat</h2>
                <div id="chat-messages" class="chat-messages"></div>
                <form id="chat-form" class="chat-form">
                    <input type="text" id="chat-input" placeholder="Say something…" maxlength="500" autocomplete="off">
                    <button type="submit" class="btn btn-small">Send</button>
                </form>
            </section>
        </div>
    </div>

    <div id="kicked-overlay" class="kicked-overlay" hidden>
        <div class="kicked-modal">
            <h2>You've been removed</h2>
            <p>The host has removed you from this lobby.</p>
            <a href="<?= htmlspecialchars($home_url, ENT_QUOTES) ?>" class="btn btn-primary">Back to Home</a>
        </div>
    </div>

    <script src="/priorities/assets/js/lobby.js"></script>

    <script>
    function copyCode() {
        const code = document.getElementById('lobby-code').textContent;
        navigator.clipboard.writeText(code).then(() => {
            const btn = document.querySelector('.btn-copy');
            btn.textContent = '✅';
            setTimeout(() => { btn.textContent = '📋'; }, 1500);
        });
    }
    </script>
</body>
</html>
