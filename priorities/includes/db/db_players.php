<?php
declare(strict_types=1);

function dbx_insert_player(PDO $db, int $lobby_id, string $name, string $token, bool $is_host, int $turn_order): int
{
    $stmt = $db->prepare(
        'INSERT INTO players (lobby_id, name, session_token, is_host, turn_order)
         VALUES (:lobby_id, :name, :token, :is_host, :turn_order)'
    );
    $stmt->execute([
        ':lobby_id' => $lobby_id,
        ':name' => $name,
        ':token' => $token,
        ':is_host' => (int) $is_host,
        ':turn_order' => $turn_order,
    ]);

    return (int) $db->lastInsertId();
}

function dbx_count_active_players_with_name(PDO $db, int $lobby_id, string $name): int
{
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM players WHERE lobby_id = :lobby_id AND name = :name AND status = 'active'"
    );
    $stmt->execute([':lobby_id' => $lobby_id, ':name' => $name]);
    return (int) $stmt->fetchColumn();
}

function dbx_count_active_players(PDO $db, int $lobby_id): int
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM players WHERE lobby_id = :lobby_id AND status = 'active'");
    $stmt->execute([':lobby_id' => $lobby_id]);
    return (int) $stmt->fetchColumn();
}

function dbx_next_turn_order(PDO $db, int $lobby_id): int
{
    $stmt = $db->prepare(
        'SELECT COALESCE(MAX(turn_order), -1) + 1 AS next_order FROM players WHERE lobby_id = :lobby_id'
    );
    $stmt->execute([':lobby_id' => $lobby_id]);
    return (int) $stmt->fetchColumn();
}

function dbx_find_active_player_in_lobby(PDO $db, int $player_id, int $lobby_id): array|false
{
    $stmt = $db->prepare(
        "SELECT id, name FROM players WHERE id = :id AND lobby_id = :lobby_id AND status = 'active' LIMIT 1"
    );
    $stmt->execute([':id' => $player_id, ':lobby_id' => $lobby_id]);
    return $stmt->fetch();
}

function dbx_mark_player_kicked(PDO $db, int $player_id): void
{
    $stmt = $db->prepare("UPDATE players SET status = 'kicked' WHERE id = :id");
    $stmt->execute([':id' => $player_id]);
}

function dbx_fetch_player_by_id(PDO $db, int $player_id): array|false
{
    $stmt = $db->prepare('SELECT * FROM players WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $player_id]);
    return $stmt->fetch();
}

function dbx_find_active_player_by_token(PDO $db, string $token): array|false
{
    $stmt = $db->prepare(
        'SELECT id, lobby_id, name, session_token, is_host, turn_order, status, joined_at
         FROM players
         WHERE session_token = :token AND status = \'active\'
         LIMIT 1'
    );
    $stmt->execute([':token' => $token]);
    return $stmt->fetch();
}

function dbx_fetch_active_players_by_lobby(PDO $db, int $lobby_id): array
{
    $stmt = $db->prepare(
        'SELECT id, lobby_id, name, session_token, is_host, turn_order, status, joined_at
         FROM players
         WHERE lobby_id = :lobby_id AND status = \'active\'
         ORDER BY turn_order ASC'
    );
    $stmt->execute([':lobby_id' => $lobby_id]);
    return $stmt->fetchAll();
}
