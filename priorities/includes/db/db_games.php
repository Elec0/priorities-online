<?php
declare(strict_types=1);

function dbx_get_game_id_by_lobby(PDO $db, int $lobby_id): int|null
{
    $stmt = $db->prepare('SELECT id FROM games WHERE lobby_id = :id LIMIT 1');
    $stmt->execute([':id' => $lobby_id]);
    $row = $stmt->fetch();
    return $row === false ? null : (int) $row['id'];
}

function dbx_count_games_for_lobby(PDO $db, int $lobby_id): int
{
    $stmt = $db->prepare('SELECT COUNT(*) FROM games WHERE lobby_id = :lobby_id');
    $stmt->execute([':lobby_id' => $lobby_id]);
    return (int) $stmt->fetchColumn();
}

function dbx_insert_game(PDO $db, int $lobby_id, string $player_letters_json, string $game_letters_json, string $deck_order_json): int
{
    $stmt = $db->prepare(
        "INSERT INTO games
         (lobby_id, current_round, target_player_index, final_decider_index,
          status, player_letters, game_letters, deck_order, state_version)
         VALUES (:lobby_id, 1, 0, 1, 'active', :pl, :gl, :deck, 1)"
    );
    $stmt->execute([
        ':lobby_id' => $lobby_id,
        ':pl' => $player_letters_json,
        ':gl' => $game_letters_json,
        ':deck' => $deck_order_json,
    ]);

    return (int) $db->lastInsertId();
}

function dbx_fetch_game_by_id(PDO $db, int $game_id): array|false
{
    $stmt = $db->prepare('SELECT * FROM games WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $game_id]);
    return $stmt->fetch();
}

function dbx_set_game_draw(PDO $db, int $game_id): void
{
    $stmt = $db->prepare("UPDATE games SET status = 'draw' WHERE id = :id");
    $stmt->execute([':id' => $game_id]);
}

function dbx_update_game_letters_and_status(PDO $db, int $game_id, string $player_letters_json, string $game_letters_json, string $status): void
{
    $stmt = $db->prepare(
        'UPDATE games SET player_letters = :pl, game_letters = :gl, status = :status WHERE id = :id'
    );
    $stmt->execute([
        ':pl' => $player_letters_json,
        ':gl' => $game_letters_json,
        ':status' => $status,
        ':id' => $game_id,
    ]);
}

function dbx_fetch_latest_state_version_by_lobby(PDO $db, int $lobby_id): int
{
    $stmt = $db->prepare('SELECT state_version FROM games WHERE lobby_id = :id ORDER BY id DESC LIMIT 1');
    $stmt->execute([':id' => $lobby_id]);
    $row = $stmt->fetch();
    return $row !== false ? (int) $row['state_version'] : 0;
}

function dbx_fetch_latest_game_by_lobby(PDO $db, int $lobby_id): array|false
{
    $stmt = $db->prepare('SELECT * FROM games WHERE lobby_id = :id ORDER BY id DESC LIMIT 1');
    $stmt->execute([':id' => $lobby_id]);
    return $stmt->fetch();
}

function dbx_update_game_deck(PDO $db, int $game_id, string $deck_json): void
{
    $stmt = $db->prepare('UPDATE games SET deck_order = :deck WHERE id = :id');
    $stmt->execute([':deck' => $deck_json, ':id' => $game_id]);
}

function dbx_fetch_game_id_and_status_by_lobby(PDO $db, int $lobby_id): array|false
{
    $stmt = $db->prepare('SELECT id, status FROM games WHERE lobby_id = :lobby_id LIMIT 1');
    $stmt->execute([':lobby_id' => $lobby_id]);
    return $stmt->fetch();
}

function dbx_delete_game_by_id(PDO $db, int $game_id): void
{
    $stmt = $db->prepare('DELETE FROM games WHERE id = :id');
    $stmt->execute([':id' => $game_id]);
}

function dbx_increment_state_version(PDO $db, int $game_id): void
{
    $stmt = $db->prepare('UPDATE games SET state_version = state_version + 1 WHERE id = :id');
    $stmt->execute([':id' => $game_id]);
}

function dbx_update_game_for_next_round(
    PDO $db,
    int $game_id,
    int $round_number,
    int $target_index,
    int $fd_index,
    string $deck_order_json
): void {
    $stmt = $db->prepare(
        'UPDATE games
         SET current_round        = :round_number,
             target_player_index  = :target_index,
             final_decider_index  = :fd_index,
             deck_order           = :deck_order
         WHERE id = :id'
    );
    $stmt->execute([
        ':round_number' => $round_number,
        ':target_index' => $target_index,
        ':fd_index' => $fd_index,
        ':deck_order' => $deck_order_json,
        ':id' => $game_id,
    ]);
}
