<?php
declare(strict_types=1);

function dbx_lobby_code_exists(PDO $db, string $code): bool
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM lobbies WHERE code = :code AND status != 'finished'");
    $stmt->execute([':code' => $code]);
    return (int) $stmt->fetchColumn() > 0;
}

function dbx_insert_lobby(PDO $db, string $code, string $token, bool $timer_enabled, int $timer_seconds): int
{
    $stmt = $db->prepare(
        "INSERT INTO lobbies (code, host_token, status, timer_enabled, timer_seconds)
         VALUES (:code, :token, 'waiting', :timer_enabled, :timer_seconds)"
    );
    $stmt->execute([
        ':code' => $code,
        ':token' => $token,
        ':timer_enabled' => (int) $timer_enabled,
        ':timer_seconds' => $timer_seconds,
    ]);

    return (int) $db->lastInsertId();
}

function dbx_find_waiting_lobby_by_code(PDO $db, string $code): array|false
{
    $stmt = $db->prepare("SELECT id FROM lobbies WHERE code = :code AND status = 'waiting' LIMIT 1");
    $stmt->execute([':code' => $code]);
    return $stmt->fetch();
}

function dbx_set_lobby_status(PDO $db, int $lobby_id, string $status): void
{
    $stmt = $db->prepare('UPDATE lobbies SET status = :status WHERE id = :id');
    $stmt->execute([':status' => $status, ':id' => $lobby_id]);
}

function dbx_lobby_timer_settings(PDO $db, int $lobby_id): array
{
    $stmt = $db->prepare('SELECT timer_enabled, timer_seconds FROM lobbies WHERE id = :id');
    $stmt->execute([':id' => $lobby_id]);
    $row = $stmt->fetch();

    $timer_enabled = $row !== false && (bool) $row['timer_enabled'];
    $timer_seconds = $row !== false ? max(10, (int) $row['timer_seconds']) : 60;

    return ['timer_enabled' => $timer_enabled, 'timer_seconds' => $timer_seconds];
}

function dbx_fetch_lobby_by_id(PDO $db, int $lobby_id): array|false
{
    $stmt = $db->prepare('SELECT * FROM lobbies WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $lobby_id]);
    return $stmt->fetch();
}

function dbx_delete_stale_lobbies(PDO $db): void
{
    $stmt = $db->prepare(
        "DELETE FROM lobbies WHERE updated_at < DATE_SUB(NOW(), INTERVAL 24 HOUR) AND status != 'playing'"
    );
    $stmt->execute();
}
