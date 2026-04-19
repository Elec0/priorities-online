<?php
require_once __DIR__ . '/includes/db.php';

$lobby_id = isset($_GET['lobby_id']) ? (int)$_GET['lobby_id'] : 0;
if (!$lobby_id) {
    header('Location: /priorities/');
    exit;
}

// Validate token and get player
$player = null;
if (!empty($_COOKIE['priorities_token'])) {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM players WHERE session_token = ? AND status = 'active' AND lobby_id = ?");
    $stmt->execute([$_COOKIE['priorities_token'], $lobby_id]);
    $player = $stmt->fetch();
}

if (!$player) {
    header('Location: /priorities/');
    exit;
}

// If already playing, redirect to game
$l_stmt = $db->prepare("SELECT status FROM lobbies WHERE id = ?");
$l_stmt->execute([$lobby_id]);
$lobby = $l_stmt->fetch();
if ($lobby && $lobby['status'] === 'playing') {
    header('Location: /priorities/game.php?lobby_id=' . $lobby_id);
    exit;
}
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
      data-is-host="<?= (int)$player['is_host'] ?>">

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
            <a href="/priorities/" class="btn btn-primary">Back to Home</a>
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
