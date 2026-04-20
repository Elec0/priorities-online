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

$lobby_id    = (int) ($_GET['lobby_id'] ?? 0);
$dev_profile = get_dev_profile();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Game — Priorities</title>
  <link rel="stylesheet" href="/priorities/assets/css/style.css">
</head>
<body>
  <div
    id="root"
    data-lobby-id="<?= htmlspecialchars((string) $lobby_id) ?>"
    data-player-id="<?= htmlspecialchars((string) $player->id) ?>"
    data-dev-profile="<?= htmlspecialchars($dev_profile) ?>"
  ></div>
  <script type="module" src="/priorities/assets/js/dist/game.js"></script>
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

$dev_profile = get_dev_profile();
$home_url = build_path('/priorities/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Priorities — Game</title>
    <link rel="stylesheet" href="/priorities/assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
</head>
<body class="game-page"
      data-lobby-id="<?= $lobby_id ?>"
      data-player-id="<?= (int)$player['id'] ?>"
      data-dev-profile="<?= htmlspecialchars($dev_profile, ENT_QUOTES) ?>">

    <div class="game-layout">
        <!-- Left sidebar: player list -->
        <aside class="sidebar sidebar-left">
            <div class="sidebar-title">Players</div>
            <ul id="player-list" class="player-list-game"></ul>
        </aside>

        <!-- Main game area -->
        <main class="game-main">
            <header class="game-header">
                <h1 class="game-logo">PRIORITIES</h1>
                <div class="game-meta">
                    <span id="round-indicator" class="round-indicator">Round 1</span>
                    <span id="phase-indicator" class="phase-indicator">Loading…</span>
                    <span id="countdown" class="countdown" hidden></span>
                </div>
            </header>

            <div id="role-banner" class="role-banner" hidden></div>

            <div id="game-content" class="game-content">
                <div class="loading-state">Connecting…</div>
            </div>

            <div id="game-over" class="game-over" hidden>
                <div class="game-over-inner">
                    <div id="game-over-banner" class="game-over-banner"></div>
                    <div id="game-over-letters" class="game-over-letters"></div>
                    <a href="<?= htmlspecialchars($home_url, ENT_QUOTES) ?>" class="btn btn-primary btn-large">Return to Home</a>
                </div>
            </div>
        </main>

        <!-- Right sidebar: letter tracker -->
        <aside class="sidebar sidebar-right">
            <div class="sidebar-title">Score</div>
            <div class="letter-tracker">
                <div class="tracker-row">
                    <div class="tracker-label">Players</div>
                    <div id="player-tiles" class="letter-tiles"></div>
                </div>
                <div class="tracker-row">
                    <div class="tracker-label">Game</div>
                    <div id="game-tiles" class="letter-tiles"></div>
                </div>
            </div>
        </aside>
    </div>

    <!-- Chat panel -->
    <div class="chat-panel">
        <div class="chat-panel-header" id="chat-toggle" onclick="toggleChat()">
            💬 Chat <span id="chat-unread" class="chat-unread" hidden></span>
        </div>
        <div id="chat-body" class="chat-body">
            <div id="chat-messages" class="chat-messages"></div>
            <form id="chat-form" class="chat-form">
                <input type="text" id="chat-input" placeholder="Say something…" maxlength="500" autocomplete="off">
                <button type="submit" class="btn btn-small">Send</button>
            </form>
        </div>
    </div>

    <script src="/priorities/assets/js/game.js"></script>
</body>
</html>
