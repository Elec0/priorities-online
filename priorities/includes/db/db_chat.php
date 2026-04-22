<?php
declare(strict_types=1);

function dbx_insert_chat_message(PDO $db, int $lobby_id, ?int $player_id, string $message): void
{
    $stmt = $db->prepare(
        'INSERT INTO chat_messages (lobby_id, player_id, message) VALUES (:lobby_id, :player_id, :message)'
    );
    $stmt->execute([
        ':lobby_id' => $lobby_id,
        ':player_id' => $player_id,
        ':message' => $message,
    ]);
}

function dbx_fetch_recent_chat(PDO $db, int $lobby_id): array
{
    $stmt = $db->prepare(
        'SELECT cm.id, cm.player_id, p.name AS player_name, cm.message, cm.created_at
         FROM chat_messages cm
         LEFT JOIN players p ON p.id = cm.player_id
         WHERE cm.lobby_id = :lobby_id
         ORDER BY cm.created_at DESC
         LIMIT 50'
    );
    $stmt->execute([':lobby_id' => $lobby_id]);
    return array_reverse($stmt->fetchAll());
}

function dbx_insert_system_chat_message(PDO $db, int $lobby_id, string $message): void
{
    $stmt = $db->prepare(
        'INSERT INTO chat_messages (lobby_id, player_id, message) VALUES (:lobby_id, NULL, :message)'
    );
    $stmt->execute([':lobby_id' => $lobby_id, ':message' => $message]);
}
