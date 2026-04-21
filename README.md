# Priorities Online

A real-time, web-based multiplayer adaptation of the cooperative party game *Priorities*.

## Overview

Players join a shared lobby using a 6-character code. Each round, one player (the **target**) secretly ranks 5 randomly-drawn cards from "love" to "loathe." The rest of the group collaborates to guess the correct order. Correctly guessed positions award the group a letter card; incorrect ones go to "the game." The first side to spell out **P-R-I-O-R-I-T-I-E-S** wins.

- **Minimum players:** 3
- **Players win:** Collect the required counts of P, R, I, O, T, E, S before the game does
- **Game wins:** The game collects all its letters first
- **Draw:** The deck runs out before either side wins

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.1+ |
| Database | MySQL 8 / MariaDB 10.2+ |
| Frontend | TypeScript 5+, React 18 |
| Build Tool | Vite 5+ |
| Real-Time Sync | Server-Sent Events (SSE) |
| Drag-and-Drop | SortableJS (CDN) |
| Caching | APCu (PHP in-process) |
| Testing | PHPUnit 11 (backend), Vitest (frontend) |
| Local Dev | Docker Compose |

## Project Structure

```
Priorities-online/
├── priorities/              # Web root (Apache DocumentRoot)
│   ├── index.php            # Home: create/join lobby
│   ├── lobby.php            # Pre-game waiting room
│   ├── game.php             # Active game board
│   ├── api/                 # JSON API endpoints
│   ├── includes/            # PHP helpers and models
│   ├── db/                  # Schema and seed script
│   └── assets/              # CSS and TypeScript source + compiled JS
├── tests/                   # PHPUnit tests
├── docker/                  # Docker-specific config files
├── docker-compose.yml
├── composer.json
├── package.json
└── vite.config.ts
```

## Local Development Setup

### Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- [Node.js](https://nodejs.org/) (LTS)

### Steps

```bash
# Install Node dependencies
npm install

# Start Docker services (PHP/Apache, MySQL, Adminer)
docker compose up

# In a separate terminal, start Vite for hot module replacement
npm run dev

# Seed the card data (one-time, run after the DB is up)
docker compose exec php php /var/www/html/priorities/db/seed_cards.php
```

| Service | URL |
|---|---|
| App | http://localhost:8000/priorities/ |
| Adminer (DB UI) | http://localhost:8080 |

The Docker config file at `docker/config.php` is mounted automatically — no manual configuration required for local development.

## Configuration

For production or custom environments, create a `config.php` file. The application looks for it in two locations (in order):

1. `priorities/config.php`
2. `../config.php` (one level above the web root — recommended for security)

```php
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'priorities');
define('DB_USER', 'your_user');
define('DB_PASS', 'your_password');
```

## Building for Production

The compiled JavaScript files in `priorities/assets/js/dist/` are committed to the repository and served as static files. To rebuild them:

```bash
npm run build
```

No Node.js runtime is required on the production server.

## Running Tests

```bash
# Run PHP unit tests
composer test

# Generate HTML coverage report (output: coverage/)
composer coverage

# Run frontend tests
npm test
```

## Game Phases

Each round progresses through the following phases:

| Phase | Who | Description |
|---|---|---|
| `ranking` | Target only | Target secretly ranks 5 cards within a 60-second deadline |
| `guessing` | All others | Group collaborates to guess the target's ranking via drag-and-drop |
| `revealed` | Final Decider | Final Decider locks in the guess; letters are awarded and scored |
| `skipped` | (automatic) | Target timed out; cards returned to the bottom of the deck |

## API Endpoints

All endpoints live at `/priorities/api/` and return JSON. Authentication uses a `priorities_token` cookie set at lobby creation or join.

| Method | Endpoint | Description |
|---|---|---|
| POST | `create_lobby.php` | Create a new lobby |
| POST | `join_lobby.php` | Join a lobby by 6-character code |
| POST | `start_game.php` | Host starts the game (shuffles deck, creates game row) |
| GET | `stream.php` | SSE stream; pushes full game state on any change |
| POST | `submit_ranking.php` | Target submits their secret card ranking |
| POST | `update_guess.php` | Group updates their collaborative guess |
| POST | `lock_in_guess.php` | Final Decider scores the round and awards letters |
| POST | `send_message.php` | Send a chat message |
| POST | `kick_player.php` | Host removes a player from the lobby |

## Production Deployment

This project is designed to run on Apache shared hosting (e.g., GoDaddy) with no Node.js runtime required.

1. Upload the `priorities/` folder to your web root
2. Place `config.php` **one level above** the web root
3. Import `priorities/db/schema.sql` into your MySQL database
4. Run `seed_cards.php` to populate the card table
5. Ensure `mod_rewrite` is enabled and `.htaccess` is allowed

### Applying DB Migrations On Deploy

This project includes a migration runner at [priorities/db/migrate.php](priorities/db/migrate.php) that:

- creates a `schema_migrations` tracking table if needed
- applies pending `.sql` files from [priorities/db/migrations](priorities/db/migrations) in filename order
- prevents edited historical migrations via checksum validation

Run in dry-run mode first:

```bash
php priorities/db/migrate.php --dry-run
```

If your database already had historical changes applied manually (before this script existed),
baseline once by marking current migration files as applied without executing SQL:

```bash
php priorities/db/migrate.php --mark-applied
```

Then apply:

```bash
php priorities/db/migrate.php
```

Or via Composer:

```bash
composer migrate
```

Recommended release order for schema changes:

1. Backup production database
2. Deploy application code
3. Run migrations
4. Restart PHP process/container (to clear APCu)
5. Smoke test lobby/game flow

## Architecture

See [architecture.md](architecture.md) for a detailed breakdown of design decisions, the SSE state sync model, APCu caching strategy, and role-based access control.
