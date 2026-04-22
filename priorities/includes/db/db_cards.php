<?php
declare(strict_types=1);

function dbx_all_card_ids(PDO $db): array
{
    $stmt = $db->prepare('SELECT id FROM cards');
    $stmt->execute();
    return array_map('intval', array_column($stmt->fetchAll(), 'id'));
}

function dbx_fetch_cards_by_ids(PDO $db, array $card_ids): array
{
    if (count($card_ids) === 0) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($card_ids), '?'));
    $stmt = $db->prepare("SELECT * FROM cards WHERE id IN ({$placeholders})");
    $stmt->execute($card_ids);
    return $stmt->fetchAll();
}

function dbx_fetch_card_letters_by_ids(PDO $db, array $card_ids): array
{
    if (count($card_ids) === 0) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($card_ids), '?'));
    $stmt = $db->prepare("SELECT letter FROM cards WHERE id IN ({$placeholders})");
    $stmt->execute($card_ids);

    return array_map(static fn(array $row) => (string) $row['letter'], $stmt->fetchAll());
}
