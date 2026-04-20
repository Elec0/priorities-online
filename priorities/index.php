<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Priorities</title>
  <link rel="stylesheet" href="/priorities/assets/css/style.css">
</head>
<body>
  <div id="root"></div>
  <script type="module" src="/priorities/assets/js/dist/index.js"></script>
</body>
</html>
<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';

$dev_multi_session = is_dev_multi_session_enabled();
$dev_profile = get_dev_profile();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Priorities — Party Game</title>
    <link rel="stylesheet" href="/priorities/assets/css/style.css">
</head>
<body class="index-page">
    <div class="index-hero">
        <h1 class="logo-title">PRIORITIES</h1>
        <p class="tagline">The cooperative party game — can you guess what matters most?</p>
    </div>

    <div class="index-cards">
        <!-- Start a Game -->
        <div class="card">
            <h2>🎮 Start a Game</h2>
            <p>Create a new lobby and invite friends with your unique code.</p>
            <form id="create-form">
                <div class="form-group">
                    <label for="create-name">Your name</label>
                    <input type="text" id="create-name" name="name" maxlength="50" placeholder="Enter your name" required>
                </div>
                <?php if ($dev_multi_session): ?>
                <div class="form-group">
                    <label for="create-dev-profile">Dev profile</label>
                    <input type="text" id="create-dev-profile" name="dev_profile" maxlength="20"
                           placeholder="host"
                           value="<?= htmlspecialchars($dev_profile, ENT_QUOTES) ?>">
                </div>
                <p>Dev mode: use a different profile name in each tab or window to simulate multiple players.</p>
                <?php endif; ?>
                <div id="create-error" class="form-error" hidden></div>
                <button type="submit" class="btn btn-primary" id="create-btn">Create Lobby</button>
            </form>
        </div>

        <!-- Join a Game -->
        <div class="card">
            <h2>🚪 Join a Game</h2>
            <p>Have a lobby code? Jump straight in!</p>
            <form id="join-form">
                <div class="form-group">
                    <label for="join-name">Your name</label>
                    <input type="text" id="join-name" name="name" maxlength="50" placeholder="Enter your name" required>
                </div>
                <?php if ($dev_multi_session): ?>
                <div class="form-group">
                    <label for="join-dev-profile">Dev profile</label>
                    <input type="text" id="join-dev-profile" name="dev_profile" maxlength="20"
                           placeholder="player2"
                           value="<?= htmlspecialchars($dev_profile, ENT_QUOTES) ?>">
                </div>
                <?php endif; ?>
                <div class="form-group">
                    <label for="join-code">Lobby code</label>
                    <input type="text" id="join-code" name="code" maxlength="6" minlength="6"
                           placeholder="XXXXXX" required
                           style="text-transform:uppercase;letter-spacing:0.15em;">
                </div>
                <div id="join-error" class="form-error" hidden></div>
                <button type="submit" class="btn btn-secondary" id="join-btn">Join Lobby</button>
            </form>
        </div>
    </div>

    <script>
    document.getElementById('join-code').addEventListener('input', function() {
        this.value = this.value.toUpperCase().replace(/[^A-Z]/g, '');
    });

    document.getElementById('create-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('create-btn');
        const errEl = document.getElementById('create-error');
        btn.disabled = true;
        btn.textContent = 'Creating…';
        errEl.hidden = true;

        const fd = new FormData(this);
        try {
            const res = await fetch('/priorities/api/create_lobby.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                window.location.href = data.redirect_url || ('/priorities/lobby.php?lobby_id=' + data.lobby_id);
            } else {
                errEl.textContent = data.error || 'Unknown error';
                errEl.hidden = false;
                btn.disabled = false;
                btn.textContent = 'Create Lobby';
            }
        } catch (err) {
            errEl.textContent = 'Network error — please try again.';
            errEl.hidden = false;
            btn.disabled = false;
            btn.textContent = 'Create Lobby';
        }
    });

    document.getElementById('join-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('join-btn');
        const errEl = document.getElementById('join-error');
        btn.disabled = true;
        btn.textContent = 'Joining…';
        errEl.hidden = true;

        const fd = new FormData(this);
        try {
            const res = await fetch('/priorities/api/join_lobby.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                window.location.href = data.redirect_url || ('/priorities/lobby.php?lobby_id=' + data.lobby_id);
            } else {
                errEl.textContent = data.error || 'Unknown error';
                errEl.hidden = false;
                btn.disabled = false;
                btn.textContent = 'Join Lobby';
            }
        } catch (err) {
            errEl.textContent = 'Network error — please try again.';
            errEl.hidden = false;
            btn.disabled = false;
            btn.textContent = 'Join Lobby';
        }
    });
    </script>
</body>
</html>
