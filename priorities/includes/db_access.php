<?php
declare(strict_types=1);

/**
 * Aggregator for DB access modules.
 * All DB query functions are split into domain files under includes/db/.
 */

require_once __DIR__ . '/db/db_lobbies.php';
require_once __DIR__ . '/db/db_players.php';
require_once __DIR__ . '/db/db_cards.php';
require_once __DIR__ . '/db/db_chat.php';
require_once __DIR__ . '/db/db_games.php';
require_once __DIR__ . '/db/db_rounds.php';
