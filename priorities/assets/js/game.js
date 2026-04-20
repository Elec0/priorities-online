/* game.js — Main game logic, polling, rendering all phases */

const lobbyId    = parseInt(document.body.dataset.lobbyId, 10);
const myPlayerId = parseInt(document.body.dataset.playerId, 10);

let stateVersion   = 0;
let pollTimer      = null;
let countdownTimer = null;
let sortableInst   = null;
let chatOpen       = true;
let lastRoundStatus = null;
let hasRenderedState = false;

// --- Polling ---

function startPolling() {
    poll();
    pollTimer = setInterval(poll, 2000);
}

async function poll() {
    try {
        const res  = await fetch(`/priorities/api/poll.php?lobby_id=${lobbyId}&state_version=${stateVersion}`);
        const data = await res.json();
        if (data.error) return;
        const nextStateVersion = parseInt(data.state_version, 10) || 0;
        const shouldRenderState = !hasRenderedState || nextStateVersion !== stateVersion;

        stateVersion = nextStateVersion;
        if (shouldRenderState) {
            renderState(data);
            hasRenderedState = true;
        }
        renderChat(data.chat || []);
    } catch (e) {}
}

// --- Top-level render ---

function renderState(state) {
    renderPlayerList(state);
    renderLetterTiles('player-tiles', state.player_letters || {}, 'player');
    renderLetterTiles('game-tiles',   state.game_letters   || {}, 'game');

    const roundIndicator = document.getElementById('round-indicator');
    if (roundIndicator && state.round) {
        roundIndicator.textContent = `Round ${state.round.number}`;
    }

    if (state.game_status && state.game_status !== 'active') {
        renderGameOver(state);
        return;
    }

    if (!state.round) return;

    const phaseEl = document.getElementById('phase-indicator');
    const phaseMap = { ranking: 'Ranking Phase', guessing: 'Guessing Phase', revealed: 'Revealed', skipped: 'Skipped' };
    if (phaseEl) phaseEl.textContent = phaseMap[state.round.status] || state.round.status;

    switch (state.round.status) {
        case 'ranking':  renderRanking(state);  break;
        case 'guessing': renderGuessing(state); break;
        case 'revealed': renderRevealed(state); break;
        default:
            document.getElementById('game-content').innerHTML = '<p class="info-text">Loading next round…</p>';
    }
}

// --- Ranking phase ---

function renderRanking(state) {
    const isTarget = myPlayerId === parseInt(state.target_player.id, 10);
    setRoleBanner(isTarget
        ? '🎯 You are the Target Player — rank the cards secretly!'
        : `⏳ Waiting for <strong>${escHtml(state.target_player.name)}</strong> to rank their cards…`
    );

    manageCountdown(state.round.ranking_deadline);

    const content = document.getElementById('game-content');

    if (!isTarget) {
        content.innerHTML = '<p class="info-text">The Target Player is secretly ranking their cards…</p>';
        return;
    }

    // Build sortable list for target
    const cards = state.round.cards;
    content.innerHTML = '';

    const instructions = document.createElement('p');
    instructions.className = 'info-text';
    instructions.textContent = 'Drag to rank from 1 (love 💚) to 5 (loathe 💔). Then submit.';
    content.appendChild(instructions);

    const list = buildCardList(cards, true);
    list.id = 'ranking-list';
    content.appendChild(list);

    destroySortable();
    sortableInst = Sortable.create(list, {
        animation: 150,
        ghostClass: 'sortable-ghost',
        dragClass:  'sortable-drag',
    });

    const submitBtn = document.createElement('button');
    submitBtn.className = 'btn btn-primary btn-large';
    submitBtn.textContent = 'Submit My Ranking';
    submitBtn.onclick = submitRanking;
    content.appendChild(submitBtn);
}

async function submitRanking() {
    const list = document.getElementById('ranking-list');
    if (!list) return;
    const ids = Array.from(list.querySelectorAll('[data-card-id]'))
                     .map(el => parseInt(el.dataset.cardId, 10));
    const btn = list.parentElement.querySelector('.btn-primary');
    if (btn) { btn.disabled = true; btn.textContent = 'Submitting…'; }

    try {
        const res  = await fetch('/priorities/api/submit_ranking.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ranking: ids }),
        });
        const data = await res.json();
        if (!data.success && btn) {
            btn.disabled = false;
            btn.textContent = 'Submit My Ranking';
            alert(data.error || 'Failed to submit');
        }
    } catch (e) {
        if (btn) { btn.disabled = false; btn.textContent = 'Submit My Ranking'; }
    }
}

// --- Guessing phase ---

let guessDebounce = null;

function renderGuessing(state) {
    clearCountdown();
    const isTarget = myPlayerId === parseInt(state.target_player.id, 10);
    const isFD     = myPlayerId === parseInt(state.final_decider.id, 10);

    if (isTarget) {
        setRoleBanner('🎯 You are the Target Player — wait while others guess!');
    } else if (isFD) {
        setRoleBanner('🔒 You are the Final Decider — arrange the cards and lock in!');
    } else {
        setRoleBanner(`Discuss and arrange the cards! <strong>${escHtml(state.final_decider.name)}</strong> will lock in.`);
    }

    const content = document.getElementById('game-content');
    content.innerHTML = '';

    const cards = state.round.cards;
    const cardMap = {};
    cards.forEach(c => { cardMap[c.id] = c; });

    // Use group_ranking order if available, else card_ids order
    const orderedIds = state.round.group_ranking || state.round.card_ids;
    const orderedCards = orderedIds.map(id => cardMap[id]).filter(Boolean);

    const instructions = document.createElement('p');
    instructions.className = 'info-text';
    if (isTarget) {
        instructions.textContent = 'The group is discussing. Sit tight!';
    } else {
        instructions.textContent = 'Drag to arrange from 1 (love 💚) to 5 (loathe 💔).';
    }
    content.appendChild(instructions);

    const list = buildCardList(orderedCards, !isTarget);
    list.id = 'guessing-list';
    content.appendChild(list);

    if (!isTarget) {
        destroySortable();
        sortableInst = Sortable.create(list, {
            animation: 150,
            ghostClass: 'sortable-ghost',
            dragClass:  'sortable-drag',
            onEnd: () => {
                clearTimeout(guessDebounce);
                guessDebounce = setTimeout(sendGuess, 400);
            },
        });
        setupTapFallback(list);
    }

    // Action buttons
    const actionsDiv = document.createElement('div');
    actionsDiv.className = 'action-buttons';

    if (isFD) {
        const lockBtn = document.createElement('button');
        lockBtn.className = 'btn btn-danger btn-large';
        lockBtn.textContent = '🔒 Lock In Guess';
        lockBtn.onclick = lockInGuess;
        actionsDiv.appendChild(lockBtn);
    } else if (!isTarget) {
        const waitMsg = document.createElement('p');
        waitMsg.className = 'info-text muted';
        waitMsg.innerHTML = `Waiting for <strong>${escHtml(state.final_decider.name)}</strong> to lock in…`;
        actionsDiv.appendChild(waitMsg);
    }

    content.appendChild(actionsDiv);
}

async function sendGuess() {
    const list = document.getElementById('guessing-list');
    if (!list) return;
    const ids = Array.from(list.querySelectorAll('[data-card-id]'))
                     .map(el => parseInt(el.dataset.cardId, 10));
    try {
        await fetch('/priorities/api/update_guess.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ranking: ids }),
        });
    } catch (e) {}
}

async function lockInGuess() {
    const btn = document.querySelector('.btn-danger');
    if (btn) { btn.disabled = true; btn.textContent = 'Locking in…'; }
    try {
        const res  = await fetch('/priorities/api/lock_in_guess.php', { method: 'POST' });
        const data = await res.json();
        if (!data.success) {
            alert(data.error || 'Failed to lock in');
            if (btn) { btn.disabled = false; btn.textContent = '🔒 Lock In Guess'; }
        }
    } catch (e) {
        if (btn) { btn.disabled = false; btn.textContent = '🔒 Lock In Guess'; }
    }
}

// --- Revealed phase ---

function renderRevealed(state) {
    clearCountdown();
    setRoleBanner('Results revealed! Next round will start automatically…');

    const content = document.getElementById('game-content');
    content.innerHTML = '';

    const round  = state.round;
    const cards  = round.cards;
    const cardMap = {};
    cards.forEach(c => { cardMap[c.id] = c; });

    const targetRanking = round.target_ranking || [];
    const groupRanking  = round.group_ranking  || [];
    const results       = round.result         || [];

    const revealDiv = document.createElement('div');
    revealDiv.className = 'reveal-container';

    const header = document.createElement('div');
    header.className = 'reveal-header';
    const correct = results.filter(r => r.correct).length;
    header.innerHTML = `<h3>${correct}/5 Correct!</h3>`;
    revealDiv.appendChild(header);

    const cols = document.createElement('div');
    cols.className = 'reveal-cols';

    const targetCol = document.createElement('div');
    targetCol.className = 'reveal-col';
    targetCol.innerHTML = `<h4>🎯 ${escHtml(state.target_player.name)}'s Ranking</h4>`;

    const groupCol = document.createElement('div');
    groupCol.className = 'reveal-col';
    groupCol.innerHTML = `<h4>👥 Group's Guess</h4>`;

    for (let i = 0; i < 5; i++) {
        const correct_i = results[i] ? results[i].correct : false;

        const tc = cardMap[targetRanking[i]];
        const gc = cardMap[groupRanking[i]];

        const tEl = document.createElement('div');
        tEl.className = `reveal-card ${correct_i ? 'reveal-correct' : 'reveal-wrong'}`;
        tEl.innerHTML = `<span class="pos-num">${i + 1}</span> <span class="card-emoji">${tc ? tc.emoji : ''}</span> <span class="card-content">${tc ? escHtml(tc.content) : '?'}</span>`;

        const gEl = document.createElement('div');
        gEl.className = `reveal-card ${correct_i ? 'reveal-correct' : 'reveal-wrong'}`;
        gEl.innerHTML = `<span class="pos-num">${i + 1}</span> <span class="card-emoji">${gc ? gc.emoji : ''}</span> <span class="card-content">${gc ? escHtml(gc.content) : '?'}</span>`;

        targetCol.appendChild(tEl);
        groupCol.appendChild(gEl);
    }

    cols.appendChild(targetCol);
    cols.appendChild(groupCol);
    revealDiv.appendChild(cols);
    content.appendChild(revealDiv);
}

// --- Game over ---

function renderGameOver(state) {
    clearInterval(pollTimer);
    clearCountdown();

    const overlay = document.getElementById('game-over');
    const banner  = document.getElementById('game-over-banner');
    const lettersDiv = document.getElementById('game-over-letters');
    if (!overlay) return;

    overlay.hidden = false;
    document.getElementById('game-content').hidden = true;

    const msgs = {
        players_win: '🎉 Players Win! You spelled PRIORITIES!',
        game_wins:   '💀 The Game Wins! It spelled PRIORITIES first.',
        draw:        '🤝 Draw! The deck ran out.',
    };
    banner.textContent = msgs[state.game_status] || 'Game Over!';
    banner.className   = `game-over-banner ${state.game_status}`;

    lettersDiv.innerHTML = `
        <div class="go-letters-row">
            <strong>Players:</strong>
            ${renderLetterString(state.player_letters || {})}
        </div>
        <div class="go-letters-row">
            <strong>Game:</strong>
            ${renderLetterString(state.game_letters || {})}
        </div>`;
}

function renderLetterString(letters) {
    const seq = ['P','R','I','O','R','I','T','I','E','S'];
    const counts = Object.assign({P:0,R:0,I:0,O:0,T:0,E:0,S:0}, letters);
    const used = {P:0,R:0,I:0,O:0,T:0,E:0,S:0};
    return seq.map(l => {
        used[l] = (used[l] || 0) + 1;
        const filled = counts[l] >= used[l];
        return `<span class="letter-tile-inline ${filled ? 'filled' : 'hollow'}">${l}</span>`;
    }).join('');
}

// --- Player list ---

function renderPlayerList(state) {
    const list = document.getElementById('player-list');
    if (!list) return;

    const targetId = state.target_player ? parseInt(state.target_player.id, 10) : -1;
    const fdId     = state.final_decider ? parseInt(state.final_decider.id, 10) : -1;

    list.innerHTML = '';
    (state.players || []).forEach(p => {
        if (p.status === 'kicked') return;
        const li = document.createElement('li');
        li.className = 'player-item-game' + (parseInt(p.id, 10) === myPlayerId ? ' me' : '');

        let badges = '';
        if (parseInt(p.id, 10) === targetId) badges += '<span class="badge badge-target" title="Target Player">🎯</span>';
        if (parseInt(p.id, 10) === fdId)     badges += '<span class="badge badge-fd" title="Final Decider">🔒</span>';
        if (parseInt(p.is_host, 10))          badges += '<span class="badge badge-host">H</span>';

        li.innerHTML = `<span class="player-name">${escHtml(p.name)}</span>${badges}`;
        list.appendChild(li);
    });
}

// --- Letter tiles ---

function renderLetterTiles(containerId, letters, side) {
    const container = document.getElementById(containerId);
    if (!container) return;
    const seq = ['P','R','I','O','R','I','T','I','E','S'];
    const counts = Object.assign({P:0,R:0,I:0,O:0,T:0,E:0,S:0}, letters);
    const used = {P:0,R:0,I:0,O:0,T:0,E:0,S:0};
    container.innerHTML = seq.map(l => {
        used[l] = (used[l] || 0) + 1;
        const filled = counts[l] >= used[l];
        return `<span class="letter-tile ${filled ? 'filled-' + side : 'hollow'}" title="${l}">${l}</span>`;
    }).join('');
}

// --- Card list builder ---

function buildCardList(cards, draggable) {
    const ol = document.createElement('ol');
    ol.className = 'card-list' + (draggable ? ' draggable' : '');
    cards.forEach((card, i) => {
        const li = document.createElement('li');
        li.className = 'card-item';
        li.dataset.cardId = card.id;
        li.innerHTML = `
            <span class="pos-num">${i + 1}</span>
            <span class="card-emoji">${card.emoji}</span>
            <span class="card-content">${escHtml(card.content)}</span>`;
        ol.appendChild(li);
    });
    return ol;
}

// Update position numbers after sort
function updatePositionNumbers(list) {
    Array.from(list.children).forEach((li, i) => {
        const num = li.querySelector('.pos-num');
        if (num) num.textContent = i + 1;
    });
}

// --- Mobile tap fallback ---

function setupTapFallback(list) {
    let selected = null;
    let touchStartX = 0, touchStartY = 0;

    list.addEventListener('touchstart', e => {
        touchStartX = e.touches[0].clientX;
        touchStartY = e.touches[0].clientY;
    }, { passive: true });

    list.addEventListener('touchend', e => {
        const dx = Math.abs(e.changedTouches[0].clientX - touchStartX);
        const dy = Math.abs(e.changedTouches[0].clientY - touchStartY);
        if (dx > 10 || dy > 10) return; // was a drag, not a tap

        const item = e.target.closest('[data-card-id]');
        if (!item) return;

        if (selected && selected !== item) {
            // Swap
            const allItems = Array.from(list.children);
            const selIdx  = allItems.indexOf(selected);
            const tapIdx  = allItems.indexOf(item);
            if (selIdx !== -1 && tapIdx !== -1) {
                if (selIdx < tapIdx) {
                    list.insertBefore(selected, item.nextSibling);
                } else {
                    list.insertBefore(selected, item);
                }
                updatePositionNumbers(list);
                clearTimeout(guessDebounce);
                guessDebounce = setTimeout(sendGuess, 400);
            }
            selected.classList.remove('selected');
            selected = null;
        } else if (selected === item) {
            selected.classList.remove('selected');
            selected = null;
        } else {
            if (selected) selected.classList.remove('selected');
            selected = item;
            item.classList.add('selected');
        }
        e.preventDefault();
    });
}

// --- Countdown timer ---

function manageCountdown(deadline) {
    clearCountdown();
    const el = document.getElementById('countdown');
    if (!el || !deadline) { if (el) el.hidden = true; return; }

    const deadlineMs = new Date(deadline).getTime();
    el.hidden = false;

    function tick() {
        const remaining = Math.max(0, Math.floor((deadlineMs - Date.now()) / 1000));
        const mins = Math.floor(remaining / 60);
        const secs = remaining % 60;
        el.textContent = `${mins}:${secs.toString().padStart(2, '0')}`;
        el.classList.toggle('countdown-warning', remaining < 15);
        if (remaining <= 0) clearCountdown();
    }
    tick();
    countdownTimer = setInterval(tick, 1000);
}

function clearCountdown() {
    if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null; }
    const el = document.getElementById('countdown');
    if (el) el.hidden = true;
}

// --- Role banner ---

function setRoleBanner(html) {
    const el = document.getElementById('role-banner');
    if (!el) return;
    el.hidden = false;
    el.innerHTML = html;
}

// --- Chat ---

function renderChat(messages) {
    const container = document.getElementById('chat-messages');
    if (!container) return;
    const wasScrolledToBottom = container.scrollHeight - container.clientHeight <= container.scrollTop + 5;

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

    if (wasScrolledToBottom || messages.length === 0) {
        container.scrollTop = container.scrollHeight;
    }
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

function toggleChat() {
    const body = document.getElementById('chat-body');
    if (!body) return;
    chatOpen = !chatOpen;
    body.style.display = chatOpen ? 'flex' : 'none';
}

// --- Helpers ---

function destroySortable() {
    if (sortableInst) { sortableInst.destroy(); sortableInst = null; }
}

function escHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// --- Init ---

startPolling();
