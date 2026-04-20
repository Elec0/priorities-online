# Priorities Online — Architecture & Summary Document

> This document is written for consumption by an LLM. It is intended to contain enough information to fully recreate the project in another framework, programming language, or architecture. All significant design decisions and their rationale are documented.

---

## 1. Project Purpose

**Priorities Online** is a web-based, real-time multiplayer adaptation of the cooperative party game *Priorities*. Players join a shared lobby, then take turns secretly ranking 5 drawn cards on a scale of "love" to "loathe." The rest of the group collaborates to guess the order. Correctly guessed positions earn the group a card; incorrectly guessed ones go to "the game." The first side (players or game) to collect enough cards to spell **P-R-I-O-R-I-T-I-E-S** wins.

**Win condition for players:** Collect ≥1 P, ≥2 R, ≥3 I, ≥1 O, ≥1 T, ≥1 E, ≥1 S.
**Win condition for the game (lose condition for players):** Same letter thresholds, but using game-collected cards.
**Draw condition:** The deck runs out before either side wins.

Player count: 3+ (minimum 3 to start). No maximum player count is enforced in code.

---

## 2. Technology Stack

| Layer | Technology | Version | Notes |
|---|---|---|---|
| Backend language | PHP | 8.1+ | Strict types (`declare(strict_types=1)`), typed properties, named arguments, match expressions, arrow functions |
| Database | MySQL / MariaDB | 8 / 10.2+ | utf8mb4 throughout; JSON column type heavily used |
| Frontend source | TypeScript | 5+ | Compiled to ES2020 JS; compiled output committed to repo and served statically |
| Frontend build (local dev) | Vite | 5+ | HMR during local development; `vite build` outputs plain JS to `priorities/assets/js/` |
| Drag-and-drop | SortableJS | 1.15.2 | CDN-loaded; used on game page only |
| Real-time sync | Server-Sent Events (SSE) | — | Client uses `EventSource`; server holds connection up to 30s and pushes on version change |
| Caching / rate limiting | APCu | — | PHP in-process shared memory; no extra server software required |
| Tests | PHPUnit | 11 | Only pure (database-free) logic is unit-tested |
| Dependency manager (PHP) | Composer | — | PSR-4 autoloading for `includes/`; PHPUnit as dev dependency |
| Local dev environment | Docker Compose | — | `php:8.1-apache` + `mysql:8` + Adminer; mirrors prod without a local LAMP install |

**Key design decision — SSE over WebSockets:** SSE (`text/event-stream`) was chosen over WebSockets because it requires no persistent server process and works on standard Apache/PHP shared hosting. The client uses the browser-native `EventSource` API; no library is required. The server holds the connection open for up to 30 seconds, polling the DB for a version change every ~300ms. When a change is detected (or timeout reached), it sends a single event and closes the connection; `EventSource` automatically reconnects.

**Key design decision — SSE over HTTP polling:** The previous implementation polled every 2 seconds unconditionally. SSE eliminates the fixed tick: state changes are delivered within ~300ms of occurring, and no payload is sent when nothing changes. Server load is reduced because the 30-second hold replaces 15 individual HTTP requests.

**Key design decision — Vanilla JS:** No frontend framework (React, Vue, etc.) was used. All DOM manipulation is imperative. This keeps the build process trivially simple while the game logic is simple enough that a framework adds no value.

**Key design decision — TypeScript compiled to static JS:** The hosting environment (GoDaddy shared hosting) serves static files only — it has no Node.js runtime. TypeScript is used for authoring only; `tsc` / `vite build` compiles to plain `.js` files in `priorities/assets/js/` which are committed to the repository and deployed directly. The host never sees TypeScript.

**Key design decision — Docker Compose for local dev only:** Docker Compose provides a one-command local environment (`docker compose up`) that matches the production LAMP stack without requiring a local Apache/MySQL/PHP install. It is never used in production; GoDaddy runs the PHP files directly. The `docker-compose.yml` lives at the project root and is not deployed.

---

## 3. Repository Structure

```
Priorities-online/
├── priorities/                   # Web root — point Apache/Nginx/PHP here
│   ├── index.php                 # Home page: create or join lobby
│   ├── lobby.php                 # Waiting room
│   ├── game.php                  # Active game board
│   ├── config.php                # DB credentials (gitignored; created by operator)
│   │
│   ├── api/                      # JSON API endpoints (all return application/json)
│   │   ├── create_lobby.php      # POST: create lobby, set auth cookie
│   │   ├── join_lobby.php        # POST: join lobby by 6-char code, set auth cookie
│   │   ├── start_game.php        # POST: host-only; shuffle deck, create game row
│   │   ├── stream.php            # GET: SSE endpoint; holds connection, pushes state on version change, enforces round timeouts
│   │   ├── submit_ranking.php    # POST: target player submits their secret ranking
│   │   ├── update_guess.php      # POST: any non-target player updates group guess
│   │   ├── lock_in_guess.php     # POST: final decider scores the round
│   │   ├── send_message.php      # POST: append chat message
│   │   └── kick_player.php       # POST: host removes a player
│   │
│   ├── includes/
│   │   ├── db.php                # PDO singleton; config file resolution
│   │   ├── session.php           # Cookie helpers + dev multi-session support
│   │   ├── auth.php              # Token validation + role guard helpers
│   │   └── game_logic.php        # Pure + DB-touching game logic functions
│   │
│   ├── db/
│   │   ├── schema.sql            # CREATE TABLE statements
│   │   └── seed_cards.php        # CLI: inserts all card rows into DB
│   │
│   └── assets/
│       ├── css/style.css         # Single global stylesheet
│       └── js/
│           ├── types.ts          # Shared TypeScript interfaces (GameState, RoundState, etc.)
│           ├── lobby.ts          # Waiting-room UI logic (TypeScript source)
│           ├── lobby.js          # Compiled output — committed, served by host
│           ├── game.ts           # Game board UI logic (TypeScript source)
│           └── game.js           # Compiled output — committed, served by host
│
├── tests/
│   ├── bootstrap.php             # PHPUnit bootstrap: stubs DB constants, loads game_logic.php
│   └── GameLogicTest.php         # Unit tests for pure game logic functions
│
├── cards.js                      # Legacy card data file; not used by the application — authoritative data is in seed_cards.php
├── composer.json                 # PSR-4 autoloading for includes/; dev dependency: phpunit/phpunit ^11
├── phpunit.xml                   # PHPUnit configuration
├── tsconfig.json                 # TypeScript compiler config (target ES2020, outDir priorities/assets/js/)
├── vite.config.ts                # Vite config for local dev HMR and production build
├── package.json                  # Dev dependencies: typescript, vite
├── docker-compose.yml            # Local dev: php:8.1-apache + mysql:8 + Adminer
└── priorities-rules.md           # Human-readable game rules document
```

**Key design decision — `priorities/` as web root:** The project separates the deployable web root (`priorities/`) from the project root. This allows `config.php` to be placed one level *above* the web root (outside the publicly accessible directory) for production security. `db.php` searches for `config.php` in `priorities/` first, then `../` relative to itself.

---

## 4. Database Schema

All tables use `InnoDB` engine, `utf8mb4` charset. Foreign keys cascade on delete.

### 4.1 `lobbies`
| Column | Type | Notes |
|---|---|---|
| `id` | INT AUTO_INCREMENT PK | |
| `code` | VARCHAR(6) UNIQUE | 6 uppercase A–Z chars; unique per active lobby |
| `host_token` | VARCHAR(64) | Same value as the host's `players.session_token` |
| `status` | ENUM('waiting','playing','finished') | |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME ON UPDATE | Used for stale-lobby detection |

**Stale lobby cleanup:** `stream.php` runs a `DELETE FROM lobbies WHERE updated_at < NOW() - INTERVAL 24 HOUR AND status != 'playing'` on every new connection. This piggybacks on regular traffic; no background job required.

### 4.2 `players`
| Column | Type | Notes |
|---|---|---|
| `id` | INT AUTO_INCREMENT PK | |
| `lobby_id` | INT FK → lobbies | CASCADE delete |
| `name` | VARCHAR(50) | Player-chosen display name |
| `session_token` | VARCHAR(64) UNIQUE | 32 random bytes, hex-encoded; doubles as auth token |
| `is_host` | TINYINT(1) | Only the lobby creator gets `is_host=1` |
| `turn_order` | INT | Assigned at join time (sequential, 0-indexed) |
| `status` | ENUM('active','kicked') | Kicked players remain in DB for history |
| `joined_at` | DATETIME | |

**Key design decision — token = credential:** The session token stored in the `players` table IS the auth credential. There is no separate session table. The token is set as an `HttpOnly`, `SameSite=Strict` cookie and validated on every API request by looking it up in `players`. Kicked or non-existent tokens return 401. This is stateless at the application layer (no PHP `$_SESSION` used).

### 4.3 `games`
| Column | Type | Notes |
|---|---|---|
| `id` | INT AUTO_INCREMENT PK | |
| `lobby_id` | INT FK → lobbies UNIQUE | One game per lobby |
| `current_round` | INT | Round number (1-indexed) |
| `target_player_index` | INT | Index into the sorted active-players array |
| `final_decider_index` | INT | Index into the sorted active-players array |
| `status` | ENUM('active','players_win','game_wins','draw') | |
| `player_letters` | JSON | `{"P":0,"R":0,"I":0,"O":0,"T":0,"E":0,"S":0}` |
| `game_letters` | JSON | Same structure |
| `deck_order` | JSON | Array of card IDs remaining in deck (shuffled at game start) |
| `state_version` | INT | Incremented on every state change; clients use this to detect when to re-render |
| `created_at` | DATETIME | |

**Key design decision — `state_version`:** Because the frontend uses SSE, re-rendering the DOM on every reconnect would cause flicker and UX issues. The `state_version` integer allows the server to detect a change and push an event, and the client to skip re-rendering when the version is unchanged. It is incremented (via `bump_version()`) on every write that changes visible game state.

**APCu state cache:** The full state payload is cached in APCu keyed by `game:{game_id}:{state_version}`. `stream.php` serves the cached payload on a version change hit, avoiding redundant JSON assembly and DB round-trips when multiple clients reconnect simultaneously after the same event. The cache entry is written once by the first requester after a version bump and expires after 60 seconds.

**Key design decision — deck as JSON array:** The shuffled deck of card IDs is stored as a JSON array in `games.deck_order`. Cards are "dealt" by splicing from the front and the remaining deck is written back. This is simple and avoids needing a separate deck table. The read-splice-write pattern is a TOCTOU race if two calls run simultaneously; this is addressed by wrapping `create_next_round` and `lock_in_guess.php` in explicit InnoDB transactions (`$db->beginTransaction()` / `$db->commit()`).

**Key design decision — player indexes not IDs in `games`:** `target_player_index` and `final_decider_index` are indexes into the sorted `players WHERE status='active' ORDER BY turn_order` result set, not direct player IDs. The actual player IDs for a round are stored in the `rounds` table. The indexes are used by `next_active_player_index()` to advance turns in a wrap-around fashion. This separation means the game table doesn't need to be updated every time a player is kicked.

### 4.4 `rounds`
| Column | Type | Notes |
|---|---|---|
| `id` | INT AUTO_INCREMENT PK | |
| `game_id` | INT FK → games | |
| `round_number` | INT | |
| `target_player_id` | INT FK → players | |
| `final_decider_id` | INT FK → players | |
| `card_ids` | JSON | Array of 5 card IDs dealt for this round |
| `target_ranking` | JSON | Array of 5 card IDs in target's secret order (NULL until submitted) |
| `group_ranking` | JSON | Array of 5 card IDs in group's current guess (updated live) |
| `result` | JSON | Array of 5 `{card_id, correct}` objects (NULL until scored) |
| `status` | ENUM('ranking','guessing','revealed','skipped') | State machine |
| `ranking_deadline` | DATETIME | 60 seconds from round creation; NULL after target submits |

**Round status state machine:**
```
ranking → guessing   (target submits ranking via submit_ranking.php)
ranking → skipped    (ranking_deadline passes; stream.php auto-skips during hold loop)
guessing → revealed  (final decider locks in via lock_in_guess.php)
```

### 4.5 `chat_messages`
| Column | Type | Notes |
|---|---|---|
| `id` | INT AUTO_INCREMENT PK | |
| `lobby_id` | INT FK → lobbies | |
| `player_id` | INT FK → players ON DELETE SET NULL | NULL for system messages |
| `message` | TEXT | |
| `created_at` | DATETIME | |

System messages (game events like "Round 3 started!") use `player_id = NULL`. The `stream.php` state event always includes the last 50 messages, returned in ascending chronological order (fetched DESC, then reversed in PHP).

### 4.6 `cards`
| Column | Type | Notes |
|---|---|---|
| `id` | INT PK | Non-sequential (gaps exist; cards were numbered during game design) |
| `content` | VARCHAR(200) | The card text, e.g. "Pineapple on pizza" |
| `category` | VARCHAR(50) | Thematic grouping: moral, silly, social, daily, survival, luxury, experience, political, negative, positive, ambiguous, figure, relationship, value |
| `emoji` | VARCHAR(10) | UTF-8 emoji displayed with the card |
| `letter` | CHAR(1) | One of P/R/I/O/T/E/S; assigned at seed time |

**Total cards:** 185 distinct card IDs in the dataset (some ID numbers are skipped; the IDs reflect the original physical game's numbering).

**Letter assignment:** The `letter` field on each card determines which letter it contributes when won. The distribution of letters in the deck is set at seed time in `seed_cards.php`. This is the mechanism by which the "spell PRIORITIES" win condition operates. The thresholds are P×1, R×2, I×3, O×1, T×1, E×1, S×1 = 10 cards needed per side.

### 4.7 Explicit Indexes

InnoDB automatically creates indexes for all `PRIMARY KEY`, `UNIQUE`, and foreign key columns. The following additional indexes are created explicitly for query performance:

| Table | Index columns | Reason |
|---|---|---|
| `lobbies` | `status` | Stale-lobby cleanup query filters by `status != 'playing'` |
| `rounds` | `status`, `ranking_deadline` | Timeout check in `stream.php` filters active rounds with past deadlines |
| `chat_messages` | `created_at` | `ORDER BY created_at DESC LIMIT 50` for every state fetch |

---

## 5. Authentication & Session Model

- On `create_lobby.php` or `join_lobby.php`: a 32-byte random token is generated via `bin2hex(random_bytes(32))` (64 hex chars), stored in `players.session_token`, and set as a cookie named `priorities_token` (HttpOnly, SameSite=Strict, path=/, 7-day expiry).
- Every subsequent API call reads this cookie and looks up the player row. If not found or status='kicked', returns 401.
- The `auth.php` helpers (`validate_token`, `require_host`, `require_is_target`, `require_is_final_decider`) encapsulate this check. They call `exit` on failure, acting as guards.

**Dev multi-session mode (`DEV_MULTI_SESSION=true`):** For local testing with multiple players from one browser, a `dev_profile` string can be passed via GET/POST. The cookie name becomes `priorities_token_{profile}`. Each profile gets its own independent session. This is a developer convenience — it is disabled by default and must be explicitly enabled in `config.php`.

**Security notes:**
- **CSRF:** No explicit CSRF token is needed because all cookies are `SameSite=Strict`. Cross-site requests will not include the auth cookie.
- **SQL injection:** All database queries use PDO prepared statements throughout. No raw string interpolation into SQL.
- **XSS:** All user-supplied strings (player names, chat messages) are escaped with `htmlspecialchars()` before being rendered in PHP pages.
- **Rate limiting:** APCu is used to enforce per-token rate limits server-side. `update_guess.php` allows a maximum of 10 requests per token per 10-second window; `send_message.php` allows 5 per 10 seconds. Requests that exceed the limit receive 429. The 400ms client-side debounce on drag events remains as a UX optimization.

---

## 6. API Endpoint Contracts

All endpoints return `Content-Type: application/json`. On error, they return a JSON object `{"error": "message"}` with an appropriate HTTP status code.

### POST `/api/create_lobby.php`
- Input: form-data `name` (string, ≤50 chars), optional `dev_profile`
- Action: Creates `lobbies` row with a random 6-char uppercase code. Creates `players` row with `is_host=1`, `turn_order=0`. Sets auth cookie.
- Output: `{success: true, code, lobby_id, player_id, redirect_url}`

### POST `/api/join_lobby.php`
- Input: form-data `name`, `code` (6 chars), optional `dev_profile`
- Action: Finds lobby by code (status='waiting'). Creates player row with next available `turn_order`. Sets auth cookie.
- Output: `{success: true, lobby_id, player_id, redirect_url}`
- Errors: 404 if lobby not found or not waiting, 409 if name already taken in lobby

### GET `/api/stream.php`
- Input: query params `lobby_id`, `state_version`
- Auth: `priorities_token` cookie required
- Response headers: `Content-Type: text/event-stream`, `Cache-Control: no-cache`, `X-Accel-Buffering: no`
- Behavior:
  1. On connection: deletes stale non-playing lobbies (updated_at > 24h old)
  2. Enters a hold loop (up to 30s), checking DB every ~300ms:
     - Checks for timed-out ranking rounds (deadline < NOW()) and calls `skip_round()` if found
     - Compares current `games.state_version` to client's `state_version`
     - On version change: breaks out of loop and sends state event
  3. If hold loop times out with no change: sends a keepalive comment (`: keepalive\n\n`) and closes; `EventSource` reconnects automatically
- Event format (on state change): `data: {<full state JSON>}\n\n`
- Full state payload (playing): `{state_version, lobby_status:'playing', lobby_code, game_id, game_status, round{id,number,status,card_ids,cards[],group_ranking,result,ranking_deadline}, target_player, final_decider, players[], player_letters, game_letters, chat[]}`
- Full state payload (waiting): `{state_version, lobby_status:'waiting', lobby_code, game_id:null, players[], chat[]}`
- Note: `target_ranking` is only included in the round payload when `round.status === 'revealed'` to prevent cheating.
- Note: Full state payload is served from APCu cache when available (keyed by `game:{game_id}:{state_version}`); a fresh payload is built and cached on cache miss.

### POST `/api/start_game.php`
- Auth: host only
- Action: Shuffles all card IDs, inserts `games` row, inserts first `rounds` row with 60s deadline, updates lobby status to 'playing'. Target=index 0, FD=index 1.
- Output: `{success: true, game_id}`

### POST `/api/submit_ranking.php`
- Auth: target player only (validated against `rounds.target_player_id`)
- Input: JSON body `{ranking: [id1, id2, id3, id4, id5]}`
- Validation: The 5 submitted IDs must be exactly the 5 dealt card IDs (sorted comparison)
- Action: Sets `rounds.target_ranking`, sets status='guessing', clears deadline, bumps version, inserts system chat
- Output: `{success: true}`

### POST `/api/update_guess.php`
- Auth: any active player except the target player
- Input: JSON body `{ranking: [id1, id2, id3, id4, id5]}`
- Validation: Same sorted ID check as submit_ranking
- Action: Updates `rounds.group_ranking`, bumps version
- Output: `{success: true}`
- Note: This is called on every drag-and-drop reorder (with a 400ms debounce). It is a live, optimistic update — any non-target player can move cards, and the latest write wins.

### POST `/api/lock_in_guess.php`
- Auth: final decider only (validated against `rounds.final_decider_id`)
- Precondition: round status='guessing' and `group_ranking` is not NULL
- Action:
  1. Calls `score_round(target_ranking, group_ranking)` → 5 `{card_id, correct}` items
  2. Calls `award_letters()` for player-won and game-won card IDs
  3. Updates round: sets result, status='revealed'
  4. Calls `check_win()` on both letter sets; sets game status accordingly
  5. If game still active: deals 5 new cards, creates next round row, advances player indexes
  6. Inserts system chat with round summary
  7. Bumps version
- Output: `{success: true}`

### POST `/api/send_message.php`
- Auth: any active player
- Input: JSON body `{message: string}` (≤256 characters; enforced server-side with a 400 error)
- Action: Inserts chat_messages row with player_id
- Output: `{success: true}`

### POST `/api/kick_player.php`
- Auth: host only
- Input: JSON body `{player_id: int}`
- Constraint: The host cannot kick themselves. Attempting to do so returns 400.
- Action: Sets `players.status = 'kicked'` for the specified player in the same lobby
- Output: `{success: true}`

---

## 7. Core Game Logic (`includes/game_logic.php`)

All PHP files use `declare(strict_types=1)`. All function signatures have typed parameters and return types. All classes use typed properties. This applies to the entire `includes/` directory, all `api/` endpoints, and all `db/` scripts.

This file is split into two conceptual groups:

### 7.1 Pure Functions (testable without DB)

**`empty_letters(): array`**
Returns `['P'=>0,'R'=>0,'I'=>0,'O'=>0,'T'=>0,'E'=>0,'S'=>0]`. Used to initialize both letter trackers.

**`check_win(array $letters): bool`**
Returns true iff P≥1, R≥2, I≥3, O≥1, T≥1, E≥1, S≥1. This is the only win condition check needed for both sides.

**`score_round(array $target_ranking, array $group_ranking): array`**
Compares two arrays of 5 card IDs position-by-position. Returns 5 items: `[['card_id' => int, 'correct' => bool], ...]`. The `card_id` is always taken from the target's ranking (not the group's), so it tracks which card each position represents.

**`deal_cards(array $deck_order, int $count = 5): array`**
Splices `$count` items from the front of the deck. Returns `[$dealt, $remaining]`. Non-destructive to caller (PHP pass-by-value).

**`next_active_player_index(array $active_players, int $current_index, int $skip_turn_order = -1): int`**
Advances to the next player index with wrap-around. Optionally skips any player whose `turn_order` equals `$skip_turn_order`. Used to advance both the Target Player and the Final Decider roles. Edge case: if all players have the skip turn_order, falls back to `($current + 1) % count`.

### 7.2 DB-Touching Functions

**`get_active_players(int $lobby_id): array`**
Returns players WHERE status='active' ORDER BY turn_order ASC.

**`award_letters(array $current_letters, array $won_card_ids, PDO $db): array`**
Looks up the `letter` field for each card ID and increments the corresponding count in the letters array.

**`bump_version(PDO $db, int $game_id): void`**
`UPDATE games SET state_version = state_version + 1`.

**`insert_system_chat(PDO $db, int $lobby_id, string $message): void`**
Inserts a chat row with `player_id = NULL`.

**`create_next_round(PDO $db, int $game_id): bool`**
Full round-creation logic: advances target/FD indexes, deals 5 cards from deck, inserts round row (60s deadline), updates games row. Wrapped in an InnoDB transaction to prevent partial state on failure. Returns false if deck has fewer than 5 cards.

**`skip_round(PDO $db, int $game_id, int $round_id, string $skipped_player_name): void`**
Marks current round as 'skipped', returns its 5 cards to the bottom of the deck, inserts system chat, calls `create_next_round`. If deck exhausted, sets game status='draw'.

---

## 8. Frontend Architecture

### 8.1 Page Flow
```
index.php → [create_lobby / join_lobby API] → lobby.php → [start_game API] → game.php
```
Each page redirect includes `lobby_id` as a query parameter. The `dev_profile` is also propagated through all URLs and form fields when dev mode is active.

### 8.2 Player Data Embedding
PHP pages pass data to JavaScript via `data-*` attributes on `<body>`:
- `data-lobby-id`: lobby ID
- `data-player-id`: authenticated player's DB ID
- `data-is-host`: '1' or '0' (lobby.php only)
- `data-dev-profile`: the current dev profile string

This avoids inline `<script>` blocks and keeps the JS files static.

### 8.3 Real-Time Architecture (`lobby.ts`, `game.ts`)
Both files follow the same pattern:
1. `startStream()` opens an `EventSource` connection to `GET /api/stream.php?lobby_id={id}&state_version={v}`.
2. On `message` event: parse the JSON payload (typed as `GameState` / `LobbyState`), update the local `state_version`, and re-render.
3. On `error` event (connection dropped or keepalive close): `EventSource` reconnects automatically; no manual retry logic is needed.
4. On lobby page: if `lobby_status` changes to 'playing' in a received event, redirect to `game.php`.
5. On game page: re-render only when `state_version` changes (tracked with `hasRenderedState` + version comparison).
6. Chat is included in every state event (last 50 messages, returned most-recent first then reversed in PHP).

**TypeScript types:** Shared interfaces (e.g., `GameState`, `RoundState`, `PlayerState`, `LetterMap`) are defined in `priorities/assets/js/types.ts` and imported by both `lobby.ts` and `game.ts`. These types mirror the JSON shapes returned by `stream.php` and serve as a contract between the PHP backend and the frontend.

### 8.4 Game State Rendering (`game.js`)
`renderState(data)` is the top-level render function. It dispatches to:
- `renderRanking(state)` — ranking phase
- `renderGuessing(state)` — guessing phase
- `renderRevealed(state)` — result reveal phase
- `renderGameOver(state)` — any non-'active' game_status

**Role-based rendering:** The same `renderGuessing` function renders differently depending on whether the current player is the target, the final decider, or a regular guesser. The target sees a read-only card list. Other players see a SortableJS-enabled draggable list. The final decider additionally sees a "Lock In Guess" button.

**Group guess synchronization:** Any non-target player can drag cards to reorder them. On drag end, a `sendGuess()` call fires with a 400ms debounce. All players see the latest `group_ranking` from the next poll, so the state converges within ~2 seconds.

### 8.5 SortableJS Integration
Used only on `game.php`. Loaded from CDN. A single `sortableInst` variable tracks the current Sortable instance. `destroySortable()` is called before recreating a new list to prevent memory leaks. The instance is created for:
- The ranking list (target player, ranking phase)
- The guessing list (non-target players, guessing phase)

**Mobile fallback:** A tap-based swap mechanism (`setupTapFallback`) is layered on top of SortableJS drag-and-drop. Tapping a card selects it (highlights it), then tapping another card swaps them. This accounts for SortableJS drag behavior being unreliable on some mobile browsers.

### 8.6 Letter Tiles Rendering
The word PRIORITIES has the letter sequence `['P','R','I','O','R','I','T','I','E','S']` (10 letters). The display iterates this sequence and marks each tile as 'filled' or 'hollow' based on whether the accumulated count meets the position (e.g., second R requires R≥2). Both player tiles and game tiles are rendered this way.

### 8.7 Countdown Timer
During the ranking phase, `game.js` displays a live countdown to `ranking_deadline` (60 seconds from round start). It uses `setInterval(tick, 1000)` and counts down using `Date.now()` vs `new Date(deadline).getTime()`. Warns visually (CSS class `countdown-warning`) when <15 seconds remain. The server enforces the timeout through `stream.php`'s hold loop.

---

## 9. Card Data

185 unique cards are defined in `seed_cards.php`, which is the single authoritative source. `cards.js` at the project root is a legacy file that is not used by the application and may be out of date — ignore it.

Cards have the following categories (used for thematic grouping but not in game mechanics):
`moral`, `silly`, `social`, `daily`, `survival`, `luxury`, `experience`, `political`, `negative`, `positive`, `ambiguous`, `figure`, `relationship`, `value`

Each card has a `letter` field (one of P/R/I/O/T/E/S) assigned at seed time. The distribution of letters across 185 cards determines the game's balance. The win condition requires 11 cards minimum per side (P×1 + R×2 + I×3 + O×1 + T×1 + E×1 + S×1), so with 185 cards in the deck (~92 rounds), there's ample opportunity to win before the deck runs out.

---

## 10. Turn Order & Role Advancement

At game start:
- Target Player = index 0 of sorted active players
- Final Decider = index 1 (next active player skipping the target)

After each round:
- Both indexes advance by 1 (wrap-around), with FD always skipping whoever is the new Target Player.

The `next_active_player_index` function handles this with the `skip_turn_order` parameter. The FD advances one step but skips the new target; if the natural next would be the new target, it skips to the one after.

**Example with 4 players [A, B, C, D]:**
- Round 1: Target=A(0), FD=B(1)
- Round 2: Target=B(1), FD=C(2) [FD advances from 1, skip B → C]
- Round 3: Target=C(2), FD=D(3)
- Round 4: Target=D(3), FD=A(0) [wraps; FD advances from 3, skip D → A]

---

## 11. Testing

Only pure (database-free) functions are unit-tested. The test bootstrap (`tests/bootstrap.php`) stubs the DB constants and requires `game_logic.php` directly. No database is needed.

Tested functions:
- `empty_letters()` — structure and zero values
- `check_win()` — win/loss for all letter combinations including boundary cases
- `score_round()` — all correct, none correct, partial; card_id sourcing
- `deal_cards()` — deals from front, default count, full deck
- `next_active_player_index()` — wrap-around, skip logic, single player, empty list, all-same-turn-order edge case

Functions with DB dependencies (`award_letters`, `create_next_round`, `skip_round`, etc.) are not tested. The README notes that these could be tested by passing a mock PDO (e.g., SQLite in-memory).

**PHPUnit configuration (`phpunit.xml`):** Standard config pointing at `tests/` directory, using `tests/bootstrap.php`. Test classes use the `Tests\` namespace (PSR-4 via Composer).

---

## 12. Configuration

`config.php` (gitignored) defines:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'priorities');
define('DB_USER', 'priorities');
define('DB_PASS', 'your_password');
define('DEV_MULTI_SESSION', false);
```

`db.php` searches for `config.php` in two locations:
1. `priorities/config.php` (web root — acceptable for local dev)
2. `../../config.php` relative to `includes/` (i.e., one level above the web root — recommended for production, keeps credentials outside the web-accessible directory)

If neither is found, it falls back to hardcoded defaults (empty password). **Do not rely on this fallback in production.**

---

## 13. Deployment

### Web server configuration
- Document root: `priorities/` directory
- All paths in the code are root-relative starting with `/priorities/` (e.g., `/priorities/api/stream.php`, `/priorities/assets/css/style.css`)
- This means the app works correctly when served at the `/priorities/` subpath on a host

### Local dev (Docker Compose — recommended)
```bash
docker compose up        # starts Apache/PHP, MySQL, and Adminer
# Adminer available at http://localhost:8080
```
The `docker-compose.yml` mounts the project root into the container; file changes are reflected immediately without a rebuild.

### Local dev (frontend — Vite)
Run alongside Docker Compose for HMR on TypeScript changes:
```bash
npm install
npm run dev   # vite dev server proxies API requests to the Docker PHP container
```
To compile TypeScript to the committed JS files without the dev server:
```bash
npm run build   # vite build → outputs to priorities/assets/js/
```
**Always run `npm run build` and commit the compiled `.js` files before deploying to production.**

### PHP built-in server (minimal local dev, no Docker)
```bash
php -S localhost:8000 -t priorities/
```

### Database setup
```sql
CREATE DATABASE priorities CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'priorities'@'localhost' IDENTIFIED BY 'password';
GRANT ALL PRIVILEGES ON priorities.* TO 'priorities'@'localhost';
```
Then: `mysql -u priorities -p priorities < priorities/db/schema.sql`
Then: `php priorities/db/seed_cards.php`

---

## 14. Key Implicit Constraints & Edge Cases

1. **Only one game per lobby** — enforced by `UNIQUE` on `games.lobby_id`. Starting a game twice returns 400.
2. **Minimum 3 players to start** — enforced in `start_game.php`.
3. **No maximum player count** — not enforced in code. Large player counts may affect balance/UX but are not blocked.
4. **Target player cannot update group guess** — enforced in `update_guess.php` with a 403.
5. **group_ranking must be set before locking in** — enforced in `lock_in_guess.php`.
6. **Ranking must contain exactly the 5 dealt cards** — both `submit_ranking.php` and `update_guess.php` sort and compare the submitted IDs against `round.card_ids`.
7. **target_ranking is secret until revealed** — `stream.php` omits `target_ranking` from the state payload unless `round.status === 'revealed'`.
8. **Kicked players are not deleted** — their rows remain with `status='kicked'`. This preserves chat history attribution.
9. **Lobby code uniqueness is enforced with a retry loop** — `create_lobby.php` generates a code and checks for duplicates in a `do-while` loop. Collision probability is negligible (26^6 ≈ 300M codes).
10. **Host disconnect has no reassignment** — if the host closes their browser or loses their cookie, they must rejoin using the lobby code and their session will resume (cookie is valid for 7 days). There is no automatic host promotion. If the host's cookie expires, the lobby becomes unmanageable (no one can start the game or kick players).
11. **Host cannot kick themselves** — `kick_player.php` returns 400 if the target `player_id` matches the host's own ID.
12. **Critical writes are wrapped in InnoDB transactions** — `create_next_round` and the scoring block in `lock_in_guess.php` each use `$db->beginTransaction()` / `$db->commit()` (with `$db->rollBack()` on exception). This prevents partial state from being written if a concurrent request or crash occurs mid-operation.
13. **Chat messages are capped at 256 characters** — enforced server-side in `send_message.php` (returns 400 if exceeded).

---

## 15. Recreating in Another Framework — Key Requirements

To recreate this project in another stack (e.g., Node.js/Express, Python/FastAPI, Rails), the following must be implemented:

1. **Authentication:** Token-based, stored in HttpOnly cookie. Token = primary key of a player session. No separate session store.
2. **State versioning:** An incrementing integer on the game record that clients use to detect changes. Increment on every state-changing write.
3. **Round state machine:** The `ranking → guessing → revealed` lifecycle with `skipped` as a terminal state for timeout.
4. **Timeout enforcement via SSE stream:** The timeout check is side-effected into the SSE hold loop in `stream.php`, not a background job. Any stream connection for an active game with a past `ranking_deadline` triggers `skip_round`.
5. **Letter scoring:** JSON objects tracking counts per letter. Win condition: P≥1, R≥2, I≥3, O≥1, T≥1, E≥1, S≥1.
6. **Player rotation:** Wrap-around advancement of two role indexes (target + FD), with FD always skipping the current target.
7. **Live group guess:** Any non-target player can update `group_ranking` at any time during the guessing phase. Last-write-wins is acceptable.
8. **Chat:** Mixed player/system messages, last 50 per lobby, always included in poll response.
9. **Stale lobby cleanup:** Delete non-playing lobbies (`status != 'playing'`) untouched for 24 hours. Opportunistically on poll.
10. **Dev multi-session:** Optional support for multiple sessions per browser via cookie name namespacing, controlled by a feature flag.
