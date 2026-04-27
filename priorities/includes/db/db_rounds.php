<?php
declare(strict_types=1);

function dbx_insert_round(PDO $db, int $game_id, int $round_number, int $target_id, int $fd_id, string $card_ids_json, bool $timer_enabled, int $timer_seconds): void
{
    $deadline_sql = $timer_enabled ? "DATE_ADD(NOW(), INTERVAL {$timer_seconds} SECOND)" : 'NULL';

    $stmt = $db->prepare(
        "INSERT INTO rounds
         (game_id, round_number, target_player_id, final_decider_id, card_ids, status, ranking_deadline)
         VALUES (:game_id, :round_number, :target_id, :fd_id, :card_ids, 'ranking', {$deadline_sql})"
    );
    $stmt->execute([
        ':game_id' => $game_id,
        ':round_number' => $round_number,
        ':target_id' => $target_id,
        ':fd_id' => $fd_id,
        ':card_ids' => $card_ids_json,
    ]);
}

function dbx_fetch_round_by_status_for_lobby(PDO $db, int $lobby_id, string $status): array|false
{
    $stmt = $db->prepare(
        "SELECT r.* FROM rounds r
         JOIN games g ON g.id = r.game_id
         WHERE g.lobby_id = :lobby_id AND r.status = :status
         ORDER BY g.id DESC, r.round_number DESC LIMIT 1"
    );
    $stmt->execute([':lobby_id' => $lobby_id, ':status' => $status]);
    return $stmt->fetch();
}

function dbx_update_round_target_ranking_to_guessing(PDO $db, int $round_id, string $ranking_json): void
{
    $stmt = $db->prepare(
        "UPDATE rounds SET target_ranking = :ranking, status = 'guessing', ranking_deadline = NULL
         WHERE id = :id"
    );
    $stmt->execute([':ranking' => $ranking_json, ':id' => $round_id]);
}

function dbx_set_round_group_ranking_if_null(PDO $db, int $round_id, string $group_ranking_json): void
{
    $stmt = $db->prepare('UPDATE rounds SET group_ranking = :gr WHERE id = :id AND group_ranking IS NULL');
    $stmt->execute([':gr' => $group_ranking_json, ':id' => $round_id]);
}

function dbx_update_round_group_ranking(PDO $db, int $round_id, string $group_ranking_json): void
{
    $stmt = $db->prepare('UPDATE rounds SET group_ranking = :gr WHERE id = :id');
    $stmt->execute([':gr' => $group_ranking_json, ':id' => $round_id]);
}

function dbx_update_round_result_revealed(PDO $db, int $round_id, string $result_json): void
{
    $stmt = $db->prepare("UPDATE rounds SET result = :result, status = 'revealed' WHERE id = :id");
    $stmt->execute([':result' => $result_json, ':id' => $round_id]);
}

function dbx_fetch_prioritized_round_for_game(PDO $db, int $game_id): array|false
{
    $stmt = $db->prepare(
        "SELECT * FROM rounds WHERE game_id = :game_id
         ORDER BY
           CASE status WHEN 'ranking' THEN 0 WHEN 'guessing' THEN 1 WHEN 'revealed' THEN 2 ELSE 3 END ASC,
           round_number DESC
         LIMIT 1"
    );
    $stmt->execute([':game_id' => $game_id]);
    return $stmt->fetch();
}

function dbx_fetch_timed_out_ranking_round_for_lobby(PDO $db, int $lobby_id): array|false
{
    $stmt = $db->prepare(
        "SELECT r.*, g.lobby_id
         FROM rounds r
         JOIN games g ON g.id = r.game_id
         WHERE r.status = 'ranking'
           AND r.ranking_deadline < NOW()
           AND g.lobby_id = :lobby_id
         LIMIT 1"
    );
    $stmt->execute([':lobby_id' => $lobby_id]);
    return $stmt->fetch();
}

function dbx_claim_round_skip(PDO $db, int $round_id): int
{
    $stmt = $db->prepare("UPDATE rounds SET status = 'skipped' WHERE id = :id AND status = 'ranking'");
    $stmt->execute([':id' => $round_id]);
    return $stmt->rowCount();
}

function dbx_mark_round_skipped(PDO $db, int $round_id): void
{
    $stmt = $db->prepare("UPDATE rounds SET status = 'skipped' WHERE id = :id");
    $stmt->execute([':id' => $round_id]);
}

function dbx_fetch_round_role_history_for_game(PDO $db, int $game_id): array
{
    $stmt = $db->prepare(
        'SELECT target_player_id, final_decider_id
         FROM rounds
         WHERE game_id = :game_id
         ORDER BY round_number ASC'
    );
    $stmt->execute([':game_id' => $game_id]);
    return $stmt->fetchAll();
}
