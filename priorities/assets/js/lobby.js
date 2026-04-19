/* lobby.js — Handles waiting room polling, player list, kick, start game, chat */

const lobbyId    = parseInt(document.body.dataset.lobbyId, 10);
const myPlayerId = parseInt(document.body.dataset.playerId, 10);
const isHost     = document.body.dataset.isHost === '1';

let stateVersion  = 0;
let pollTimer     = null;
let lastChatId    = null;
let kickedByHost  = false;

function startPolling() {
    poll();
    pollTimer = setInterval(poll, 2000);
}

async function poll() {
    try {
        const res  = await fetch(`/priorities/api/poll.php?lobby_id=${lobbyId}&state_version=${stateVersion}`);
        const data = await res.json();
        if (data.error) return;

        if (data.lobby_status === 'playing') {
            clearInterval(pollTimer);
            window.location.href = `/priorities/game.php?lobby_id=${lobbyId}`;
            return;
        }

        // Only render lobby UI when in the waiting state
        if (data.lobby_status !== 'waiting') return;

        stateVersion = data.state_version;
        renderLobby(data);
        renderChat(data.chat || []);
    } catch (e) {
        // network hiccup — keep polling
    }
}

function renderLobby(data) {
    // Guard: don't render if we're already handling a kick
    if (kickedByHost) return;

    const players = data.players || [];
    const active  = players.filter(p => p.status === 'active');

    // Update code display
    const codeEl = document.getElementById('lobby-code');
    if (codeEl && data.lobby_code) codeEl.textContent = data.lobby_code;

    // Guard: skip if player ID is invalid (shouldn't happen, but be defensive)
    if (isNaN(myPlayerId) || myPlayerId <= 0) {
        console.error('[lobby] myPlayerId is invalid:', myPlayerId);
        return;
    }

    // Check if I was kicked — only act on explicit 'kicked' status, never on missing player
    const me = players.find(p => parseInt(p.id, 10) === myPlayerId);
    if (me && me.status === 'kicked') {
        kickedByHost = true;
        clearInterval(pollTimer);
        document.getElementById('kicked-overlay').hidden = false;
        return;
    }

    // If me is not in the list at all, treat as a transient poll error and skip render
    if (!me) {
        console.warn('[lobby] Current player not found in poll response — skipping render', { myPlayerId, players });
        return;
    }

    // Update player count
    const countEl = document.getElementById('player-count');
    if (countEl) countEl.textContent = `(${active.length})`;

    // Render player list
    const listEl = document.getElementById('player-list');
    if (!listEl) return;
    listEl.innerHTML = '';

    active.forEach(p => {
        const li = document.createElement('li');
        li.className = 'player-item';

        const nameSpan = document.createElement('span');
        nameSpan.className = 'player-name';
        nameSpan.textContent = p.name;
        if (parseInt(p.is_host, 10)) {
            const badge = document.createElement('span');
            badge.className = 'badge badge-host';
            badge.textContent = 'Host';
            nameSpan.appendChild(badge);
        }
        li.appendChild(nameSpan);

        if (isHost && !parseInt(p.is_host, 10) && parseInt(p.id, 10) !== myPlayerId) {
            const kickBtn = document.createElement('button');
            kickBtn.className = 'btn-kick';
            kickBtn.textContent = '✕';
            kickBtn.title = `Remove ${p.name}`;
            kickBtn.onclick = () => kickPlayer(parseInt(p.id, 10), p.name);
            li.appendChild(kickBtn);
        }

        listEl.appendChild(li);
    });

    // Update start button
    if (isHost) {
        const startBtn = document.getElementById('start-btn');
        const hintEl   = document.getElementById('start-hint');
        if (startBtn) {
            startBtn.disabled = active.length < 3;
        }
        if (hintEl) {
            hintEl.hidden = active.length >= 3;
        }
    }
}

async function kickPlayer(playerId, name) {
    if (!confirm(`Remove ${name} from the lobby?`)) return;
    try {
        await fetch('/priorities/api/kick_player.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ player_id: playerId }),
        });
    } catch (e) {}
}

const startBtn = document.getElementById('start-btn');
if (startBtn) {
    startBtn.addEventListener('click', async () => {
        startBtn.disabled = true;
        startBtn.textContent = 'Starting…';
        try {
            const res  = await fetch('/priorities/api/start_game.php', { method: 'POST' });
            const data = await res.json();
            if (data.success) {
                clearInterval(pollTimer);
                window.location.href = `/priorities/game.php?lobby_id=${lobbyId}`;
            } else {
                alert(data.error || 'Failed to start game');
                startBtn.disabled = false;
                startBtn.textContent = 'Start Game';
            }
        } catch (e) {
            alert('Network error');
            startBtn.disabled = false;
            startBtn.textContent = 'Start Game';
        }
    });
}

// Chat
function renderChat(messages) {
    const container = document.getElementById('chat-messages');
    if (!container) return;
    container.innerHTML = '';
    messages.forEach(msg => {
        const div = document.createElement('div');
        div.className = msg.player_name === 'System' ? 'chat-msg chat-system' : 'chat-msg';
        const time = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        if (msg.player_name === 'System') {
            div.textContent = msg.message;
        } else {
            div.innerHTML = `<span class="chat-author">${escHtml(msg.player_name)}</span> <span class="chat-time">${time}</span><br>${escHtml(msg.message)}`;
        }
        container.appendChild(div);
    });
    container.scrollTop = container.scrollHeight;
}

document.getElementById('chat-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const input = document.getElementById('chat-input');
    const message = input.value.trim();
    if (!message) return;
    input.value = '';
    try {
        await fetch('/priorities/api/send_message.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message }),
        });
    } catch (e) {}
});

function escHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

startPolling();
