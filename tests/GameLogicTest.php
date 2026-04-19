<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the pure (database-free) functions in includes/game_logic.php.
 */
class GameLogicTest extends TestCase
{
    // -----------------------------------------------------------------------
    // empty_letters
    // -----------------------------------------------------------------------

    public function testEmptyLettersReturnsAllZero(): void
    {
        $letters = empty_letters();
        foreach (['P', 'R', 'I', 'O', 'T', 'E', 'S'] as $key) {
            $this->assertArrayHasKey($key, $letters);
            $this->assertSame(0, $letters[$key]);
        }
        $this->assertCount(7, $letters);
    }

    // -----------------------------------------------------------------------
    // check_win
    // -----------------------------------------------------------------------

    public function testCheckWinReturnsFalseForEmptyLetters(): void
    {
        $this->assertFalse(check_win(empty_letters()));
    }

    public function testCheckWinReturnsTrueWhenAllLettersMet(): void
    {
        $this->assertTrue(check_win(['P' => 1, 'R' => 2, 'I' => 3, 'O' => 1, 'T' => 1, 'E' => 1, 'S' => 1]));
    }

    public function testCheckWinReturnsTrueWhenLettersExceedMinimum(): void
    {
        $this->assertTrue(check_win(['P' => 5, 'R' => 5, 'I' => 5, 'O' => 5, 'T' => 5, 'E' => 5, 'S' => 5]));
    }

    public function testCheckWinReturnsFalseWhenRIsShort(): void
    {
        // R needs >=2
        $this->assertFalse(check_win(['P' => 1, 'R' => 1, 'I' => 3, 'O' => 1, 'T' => 1, 'E' => 1, 'S' => 1]));
    }

    public function testCheckWinReturnsFalseWhenIIsShort(): void
    {
        // I needs >=3
        $this->assertFalse(check_win(['P' => 1, 'R' => 2, 'I' => 2, 'O' => 1, 'T' => 1, 'E' => 1, 'S' => 1]));
    }

    #[DataProvider('missingLetterProvider')]
    public function testCheckWinReturnsFalseWhenAnyRequiredLetterIsMissing(string $missing): void
    {
        $letters = ['P' => 1, 'R' => 2, 'I' => 3, 'O' => 1, 'T' => 1, 'E' => 1, 'S' => 1];
        $letters[$missing] = 0;
        $this->assertFalse(check_win($letters));
    }

    public static function missingLetterProvider(): array
    {
        return [['P'], ['R'], ['I'], ['O'], ['T'], ['E'], ['S']];
    }

    // -----------------------------------------------------------------------
    // score_round
    // -----------------------------------------------------------------------

    public function testScoreRoundAllCorrect(): void
    {
        $ranking = [1, 2, 3, 4, 5];
        $results = score_round($ranking, $ranking);

        foreach ($results as $i => $item) {
            $this->assertSame($ranking[$i], $item['card_id']);
            $this->assertTrue($item['correct']);
        }
    }

    public function testScoreRoundNoneCorrect(): void
    {
        $target = [1, 2, 3, 4, 5];
        $group  = [5, 4, 3, 2, 1];

        $results = score_round($target, $group);

        $this->assertFalse($results[0]['correct']); // 1 vs 5
        $this->assertFalse($results[1]['correct']); // 2 vs 4
        $this->assertTrue($results[2]['correct']);  // 3 vs 3 — middle matches
        $this->assertFalse($results[3]['correct']); // 4 vs 2
        $this->assertFalse($results[4]['correct']); // 5 vs 1
    }

    public function testScoreRoundPartiallyCorrect(): void
    {
        $target = [10, 20, 30, 40, 50];
        $group  = [10, 99, 30, 99, 50];

        $results = score_round($target, $group);

        $this->assertTrue($results[0]['correct']);
        $this->assertFalse($results[1]['correct']);
        $this->assertTrue($results[2]['correct']);
        $this->assertFalse($results[3]['correct']);
        $this->assertTrue($results[4]['correct']);
    }

    public function testScoreRoundCardIdsComefromTargetRanking(): void
    {
        $target = [7, 8, 9, 10, 11];
        $group  = [9, 8, 7, 10, 11];

        $results = score_round($target, $group);

        // card_id should always be from target, not group
        $this->assertSame(7, $results[0]['card_id']);
        $this->assertSame(8, $results[1]['card_id']);
        $this->assertSame(9, $results[2]['card_id']);
    }

    // -----------------------------------------------------------------------
    // deal_cards
    // -----------------------------------------------------------------------

    public function testDealCardsRemovesDealtCardsFromDeck(): void
    {
        $deck = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
        [$dealt, $remaining] = deal_cards($deck, 5);

        $this->assertCount(5, $dealt);
        $this->assertCount(5, $remaining);
        $this->assertSame([1, 2, 3, 4, 5], $dealt);
        $this->assertSame([6, 7, 8, 9, 10], $remaining);
    }

    public function testDealCardsDefaultsToFiveCards(): void
    {
        $deck = range(1, 20);
        [$dealt, $remaining] = deal_cards($deck);

        $this->assertCount(5, $dealt);
        $this->assertCount(15, $remaining);
    }

    public function testDealCardsDealFromFrontOfDeck(): void
    {
        $deck = [42, 43, 44, 99, 100];
        [$dealt, $remaining] = deal_cards($deck, 3);

        $this->assertSame([42, 43, 44], $dealt);
        $this->assertSame([99, 100], $remaining);
    }

    public function testDealCardsEntireDeckCanBeDealt(): void
    {
        $deck = [1, 2, 3, 4, 5];
        [$dealt, $remaining] = deal_cards($deck, 5);

        $this->assertSame([1, 2, 3, 4, 5], $dealt);
        $this->assertSame([], $remaining);
    }

    // -----------------------------------------------------------------------
    // next_active_player_index
    // -----------------------------------------------------------------------

    /** Build a minimal player row with a turn_order field. */
    private static function makePlayers(int ...$turnOrders): array
    {
        return array_map(
            fn(int $to) => ['turn_order' => $to, 'id' => $to],
            $turnOrders
        );
    }

    public function testNextPlayerWrapsAround(): void
    {
        $players = self::makePlayers(1, 2, 3);
        // From last player (index 2), should wrap to index 0
        $this->assertSame(0, next_active_player_index($players, 2));
    }

    public function testNextPlayerSimpleAdvance(): void
    {
        $players = self::makePlayers(1, 2, 3);
        $this->assertSame(1, next_active_player_index($players, 0));
        $this->assertSame(2, next_active_player_index($players, 1));
    }

    public function testNextPlayerSkipsGivenTurnOrder(): void
    {
        $players = self::makePlayers(1, 2, 3);
        // From index 0, skip turn_order 2 → should return index 2 (turn_order 3)
        $this->assertSame(2, next_active_player_index($players, 0, 2));
    }

    public function testNextPlayerWithSinglePlayer(): void
    {
        $players = self::makePlayers(1);
        $this->assertSame(0, next_active_player_index($players, 0));
    }

    public function testNextPlayerWithEmptyList(): void
    {
        $this->assertSame(0, next_active_player_index([], 0));
    }

    public function testNextPlayerSkipWrapsCorrectly(): void
    {
        // [0=>to1, 1=>to2, 2=>to3]. From index 2, skip to1 → index 1 (to2)
        $players = self::makePlayers(1, 2, 3);
        $this->assertSame(1, next_active_player_index($players, 2, 1));
    }

    public function testNextPlayerAllPlayersMatchSkipReturnsFallback(): void
    {
        // All players have the same turn_order (edge case guard)
        $players = self::makePlayers(5, 5, 5);
        // Should not infinite-loop; returns ($current + 1) % count
        $result = next_active_player_index($players, 0, 5);
        $this->assertSame(1, $result);
    }
}
