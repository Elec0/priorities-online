# Priorities Online

A web-based multiplayer adaptation of the **Priorities** cooperative party game. Players take turns secretly ranking five cards, while the rest of the group tries to guess the order. Win cards for correct guesses and try to spell **P-R-I-O-R-I-T-I-E-S** before the game does.

## Table of Contents

- [Game Overview](#game-overview)
- [Tech Stack](#tech-stack)
- [Project Structure](#project-structure)
- [Requirements](#requirements)
- [Setup](#setup)
- [Database](#database)
- [Configuration](#configuration)
- [Running Locally](#running-locally)
- [Testing](#testing)
- [API Reference](#api-reference)

---

## Game Overview

- **Players:** 3–6 (best with 4–5)
- **Rounds:** Each round, one player (the **Target Player**) secretly ranks 5 cards from most to least important.
- **Guessing:** The other players discuss and agree on an order. The **Final Decider** locks in the group's guess.
- **Scoring:** Each position that matches earns the players that card. Mismatches go to "the game".
- **Win condition:** Collectively spell PRIORITIES with won cards (P×1, R×2, I×3, O×1, T×1, E×1, S×1) before the game does.
- **Lose condition:** The game spells PRIORITIES first.
- **Draw:** The deck runs out before either side wins.

Stale lobbies (not playing, untouched for 24 hours) are cleaned up automatically on each poll.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.1+ |
| Database | MySQL 8 / MariaDB |
| Frontend | Vanilla JS, SortableJS (CDN) |
| Realtime | Client-side polling (`/api/poll.php`) |
| Tests | PHPUnit 11 |

---

## Project Structure

```
Priorities-online/
├── priorities/                 # Web root (point your server here or at parent)
│   ├── index.php               # Home — create / join lobby
│   ├── lobby.php               # Waiting room
│   ├── game.php                # Game board
│   ├── config.php              # DB credentials (not committed)
│   ├── api/                    # JSON API endpoints
│   │   ├── create_lobby.php
│   │   ├── join_lobby.php
│   │   ├── start_game.php
│   │   ├── poll.php            # State polling + timeout handling
│   │   ├── submit_ranking.php
│   │   ├── update_guess.php
│   │   ├── lock_in_guess.php
│   │   ├── send_message.php
│   │   └── kick_player.php
│   ├── includes/
│   │   ├── db.php              # PDO singleton
│   │   ├── auth.php            # Token validation helpers
│   │   └── game_logic.php      # Pure game logic functions
│   ├── db/
│   │   ├── schema.sql          # Table definitions
│   │   └── seed_cards.php      # Seed card data into DB
│   └── assets/
│       ├── css/style.css
│       └── js/
│           ├── lobby.js
│           └── game.js
├── tests/                      # PHPUnit test suite
│   ├── bootstrap.php           # Test bootstrap (no DB required)
│   └── GameLogicTest.php       # Unit tests for game_logic.php
├── cards.js                    # Card data (used by seed script)
├── composer.json
├── phpunit.xml
└── priorities-rules.md
```

---

## Requirements

- PHP 8.1 or later (with `pdo_mysql` extension)
- MySQL 8 or MariaDB
- Composer
- A web server (Apache or Nginx) with URL rewriting, **or** PHP's built-in server for local dev

---

## Setup

### 1. Clone the repository

```bash
git clone https://github.com/your-username/Priorities-online.git
cd Priorities-online
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Create the database

```sql
CREATE DATABASE priorities CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'priorities'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON priorities.* TO 'priorities'@'localhost';
FLUSH PRIVILEGES;
```

### 4. Apply the schema

```bash
mysql -u priorities -p priorities < priorities/db/schema.sql
```

### 5. Seed cards

```bash
php priorities/db/seed_cards.php
```

---

## Configuration

Create `priorities/config.php` (this file is gitignored):

```php
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'priorities');
define('DB_USER', 'priorities');
define('DB_PASS', 'your_password');
define('DEV_MULTI_SESSION', false);
```

The app also checks for a `config.php` one directory above `priorities/` (i.e. alongside it, outside the web root), which is the preferred location for production.

Set `DEV_MULTI_SESSION` to `true` for local development if you want to simulate multiple players from one browser.

---

## Running Locally

Point your web server's document root at the repository root, or use PHP's built-in server:

```bash
php -S localhost:8000 -t priorities/
```

Then open [http://localhost:8000](http://localhost:8000).

> **Apache / virtual host:** Set `DocumentRoot` to the `priorities/` directory, or configure an alias/subdirectory. The app uses root-relative paths (e.g. `/priorities/api/...`), so a subdirectory setup at `/priorities` works out of the box.

---

## Testing

The test suite covers the pure (database-free) game logic in `priorities/includes/game_logic.php`. No database connection is required to run the tests.

### Run all tests

```bash
composer test
# equivalent: ./vendor/bin/phpunit
```

### Run with verbose output

```bash
./vendor/bin/phpunit --testdox
```

### Run a specific test file

```bash
./vendor/bin/phpunit tests/GameLogicTest.php
```

### What's tested

| Function | Tests |
|---|---|
| `empty_letters()` | Returns correct zero-filled structure |
| `check_win()` | Win/loss conditions, all threshold combinations |
| `score_round()` | All correct, none correct, partial matches, card ID sourcing |
| `deal_cards()` | Dealing from front of deck, default count, full deck deal |
| `next_active_player_index()` | Wrap-around, skip logic, single player, empty list, edge cases |

### Adding new tests

Add test classes under `tests/`, following the `Tests\` namespace (PSR-4 autoloaded via `composer.json`). The bootstrap at `tests/bootstrap.php` pre-loads `game_logic.php` with DB constants stubbed out, so any test of pure functions works without a live database.

For tests that need database interaction, pass a mock or in-memory PDO (e.g. SQLite) directly to the function under test.

### Dev multi-session mode

When `DEV_MULTI_SESSION` is enabled, the home page shows a **Dev profile** field on create/join forms.

Use a different profile value in each tab or window, for example:

1. `host`
2. `p2`
3. `p3`

Each profile gets its own auth cookie, so you can create a lobby in one tab and join it from other tabs in the same browser without the sessions overwriting each other.

---

## API Reference

All endpoints return JSON. Authentication uses an `HttpOnly` cookie (`priorities_token`) set on lobby creation or join.

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| `POST` | `/api/create_lobby.php` | — | Create a lobby; sets session cookie |
| `POST` | `/api/join_lobby.php` | — | Join a lobby by code; sets session cookie |
| `GET` | `/api/poll.php` | ✓ | Poll game state; handles round timeouts |
| `POST` | `/api/start_game.php` | Host | Start the game |
| `POST` | `/api/submit_ranking.php` | Target player | Submit secret ranking |
| `POST` | `/api/update_guess.php` | Any player | Update the live group guess |
| `POST` | `/api/lock_in_guess.php` | Final Decider | Lock in and score the group guess |
| `POST` | `/api/send_message.php` | ✓ | Send a chat message |
| `POST` | `/api/kick_player.php` | Host | Kick a player from the lobby |
