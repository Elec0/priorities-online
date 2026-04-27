<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Priorities\Models\LetterMap;
use Priorities\Models\Player;
use Priorities\Models\Round;

class GameLogicTest extends TestCase
{
    // ── empty_letters ─────────────────────────────────────────────────────

    public function test_empty_letters_returns_all_zero(): void
    {
        $map = empty_letters();
        $this->assertSame(0, $map->P);
        $this->assertSame(0, $map->R);
        $this->assertSame(0, $map->I);
        $this->assertSame(0, $map->O);
        $this->assertSame(0, $map->T);
        $this->assertSame(0, $map->E);
        $this->assertSame(0, $map->S);
    }

    // ── check_win / LetterMap::checkWin ────────────────────────────────────

    public function test_check_win_false_when_all_zero(): void
    {
        $this->assertFalse(check_win(empty_letters()));
    }

    public function test_check_win_true_at_exact_thresholds(): void
    {
        $map = new LetterMap(P:1, R:2, I:3, O:1, T:1, E:1, S:1);
        $this->assertTrue(check_win($map));
    }

    public function test_check_win_true_above_thresholds(): void
    {
        $map = new LetterMap(P:5, R:5, I:5, O:5, T:5, E:5, S:5);
        $this->assertTrue(check_win($map));
    }

    public function test_check_win_false_r_below_threshold(): void
    {
        $map = new LetterMap(P:1, R:1, I:3, O:1, T:1, E:1, S:1);
        $this->assertFalse(check_win($map));
    }

    public function test_check_win_false_i_below_threshold(): void
    {
        $map = new LetterMap(P:1, R:2, I:2, O:1, T:1, E:1, S:1);
        $this->assertFalse(check_win($map));
    }

    public function test_check_win_false_one_letter_missing(): void
    {
        $map = new LetterMap(P:1, R:2, I:3, O:0, T:1, E:1, S:1);
        $this->assertFalse(check_win($map));
    }

    // ── LetterMap::withIncrement ────────────────────────────────────────────

    public function test_withIncrement_increments_correct_letter(): void
    {
        $base = new LetterMap(P:0, R:1, I:2, O:0, T:0, E:0, S:0);

        $result = $base->withIncrement('R');

        $this->assertSame(2, $result->R);
        // Other letters unchanged.
        $this->assertSame(0, $result->P);
        $this->assertSame(2, $result->I);
    }

    public function test_withIncrement_is_immutable(): void
    {
        $base   = new LetterMap(P:1, R:0, I:0, O:0, T:0, E:0, S:0);
        $result = $base->withIncrement('P');

        $this->assertSame(1, $base->P);   // original unchanged
        $this->assertSame(2, $result->P); // new instance incremented
        $this->assertNotSame($base, $result);
    }

    public function test_withIncrement_all_letters(): void
    {
        $base = empty_letters();
        foreach (['P','R','I','O','T','E','S'] as $letter) {
            $result = $base->withIncrement($letter);
            $this->assertSame(1, $result->$letter);
        }
    }

    // ── score_round ────────────────────────────────────────────────────────

    private function makeRound(array $target, array $group): Round
    {
        return new Round(
            id:              1,
            gameId:          1,
            roundNumber:     1,
            targetPlayerId:  1,
            finalDeciderId:  2,
            cardIds:         $target,
            targetRanking:   $target,
            groupRanking:    $group,
            result:          null,
            status:          'guessing',
            rankingDeadline: null,
        );
    }

    public function test_score_round_all_correct(): void
    {
        $order = [10, 20, 30, 40, 50];
        $round = $this->makeRound($order, $order);
        $results = score_round($round);

        $this->assertCount(5, $results);
        foreach ($results as $r) {
            $this->assertTrue($r->correct);
        }
    }

    public function test_score_round_none_correct(): void
    {
        $target = [10, 20, 30, 40, 50];
        $group  = [50, 40, 30, 20, 10];
        $round  = $this->makeRound($target, $group);
        $results = score_round($round);

        // Middle card (30) matches by coincidence.
        $this->assertFalse($results[0]->correct);
        $this->assertFalse($results[1]->correct);
        $this->assertTrue($results[2]->correct);  // 30 == 30
        $this->assertFalse($results[3]->correct);
        $this->assertFalse($results[4]->correct);
    }

    public function test_score_round_partial(): void
    {
        $target  = [10, 20, 30, 40, 50];
        $group   = [10, 20, 50, 40, 30]; // positions 0,1,3 correct
        $round   = $this->makeRound($target, $group);
        $results = score_round($round);

        $this->assertTrue($results[0]->correct);
        $this->assertTrue($results[1]->correct);
        $this->assertFalse($results[2]->correct);
        $this->assertTrue($results[3]->correct);
        $this->assertFalse($results[4]->correct);
    }

    public function test_score_round_card_id_taken_from_target(): void
    {
        $target  = [10, 20, 30, 40, 50];
        $group   = [50, 40, 30, 20, 10];
        $round   = $this->makeRound($target, $group);
        $results = score_round($round);

        // cardId should always be from the target ranking.
        $this->assertSame(10, $results[0]->cardId);
        $this->assertSame(20, $results[1]->cardId);
        $this->assertSame(30, $results[2]->cardId);
        $this->assertSame(40, $results[3]->cardId);
        $this->assertSame(50, $results[4]->cardId);
    }

    // ── deal_cards ─────────────────────────────────────────────────────────

    public function test_deal_cards_deals_from_front(): void
    {
        $deck = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
        [$dealt, $remaining] = deal_cards($deck);

        $this->assertSame([1, 2, 3, 4, 5], $dealt);
        $this->assertSame([6, 7, 8, 9, 10], $remaining);
    }

    public function test_deal_cards_default_count_is_five(): void
    {
        [$dealt, ] = deal_cards(range(1, 20));
        $this->assertCount(5, $dealt);
    }

    public function test_deal_cards_does_not_mutate_original(): void
    {
        $deck = [1, 2, 3, 4, 5, 6];
        deal_cards($deck);
        $this->assertCount(6, $deck);
    }

    public function test_deal_cards_full_deck_of_exactly_five(): void
    {
        [$dealt, $remaining] = deal_cards([1, 2, 3, 4, 5]);
        $this->assertCount(5, $dealt);
        $this->assertCount(0, $remaining);
    }

    // ── next_active_player_index ───────────────────────────────────────────

    private function makePlayers(int ...$turnOrders): array
    {
        return array_map(function (int $to) {
            return new Player(
                id:           $to,
                lobbyId:      1,
                name:         "Player{$to}",
                sessionToken: str_repeat('a', 64),
                isHost:       false,
                turnOrder:    $to,
                status:       'active',
                joinedAt:     '2024-01-01 00:00:00',
            );
        }, $turnOrders);
    }

    public function test_next_player_simple_advance(): void
    {
        $players = $this->makePlayers(0, 1, 2, 3);
        $this->assertSame(1, next_active_player_index($players, 0));
        $this->assertSame(2, next_active_player_index($players, 1));
        $this->assertSame(3, next_active_player_index($players, 2));
    }

    public function test_next_player_wraps_around(): void
    {
        $players = $this->makePlayers(0, 1, 2, 3);
        $this->assertSame(0, next_active_player_index($players, 3));
    }

    public function test_next_player_skips_given_turn_order(): void
    {
        $players = $this->makePlayers(0, 1, 2, 3);
        // Advance from index 0, skip turn_order=1 → should land on index 2.
        $this->assertSame(2, next_active_player_index($players, 0, 1));
    }

    public function test_next_player_skip_wraps_correctly(): void
    {
        $players = $this->makePlayers(0, 1, 2, 3);
        // Advance from index 3, skip turn_order=0 → should land on index 1.
        $this->assertSame(1, next_active_player_index($players, 3, 0));
    }

    public function test_next_player_single_player_returns_zero(): void
    {
        $players = $this->makePlayers(0);
        $this->assertSame(0, next_active_player_index($players, 0));
    }

    public function test_next_player_empty_list_returns_zero(): void
    {
        $this->assertSame(0, next_active_player_index([], 0));
    }

    public function test_next_player_all_same_turn_order_falls_back(): void
    {
        // All players have turn_order=0. Skip turn_order=0 → fallback: (current+1) % count.
        $players = $this->makePlayers(0, 0, 0);
        $this->assertSame(1, next_active_player_index($players, 0, 0));
        $this->assertSame(2, next_active_player_index($players, 1, 0));
        $this->assertSame(0, next_active_player_index($players, 2, 0));
    }

    // ── pick_role_player_index (semi-random no-repeat roles) ───────────────

    public function test_pick_role_player_index_avoids_repeat_until_everyone_served(): void
    {
        $players = $this->makePlayers(0, 1, 2, 3);
        $history = [0, 2, 3];

        $picked = pick_role_player_index(
            $players,
            $history,
            [],
            static fn(array $eligibleIds): int => $eligibleIds[0]
        );

        // Only player 1 is unserved in current cycle.
        $this->assertSame(1, $picked);
    }

    public function test_pick_role_player_index_resets_cycle_after_all_served(): void
    {
        $players = $this->makePlayers(0, 1, 2);
        $history = [0, 1, 2];

        $picked = pick_role_player_index(
            $players,
            $history,
            [],
            static fn(array $eligibleIds): int => $eligibleIds[count($eligibleIds) - 1]
        );

        // Cycle resets to all active players; deterministic picker chooses last => index 2.
        $this->assertSame(2, $picked);
    }

    public function test_pick_role_player_index_respects_excluded_player(): void
    {
        $players = $this->makePlayers(0, 1, 2);
        $history = [0, 1];

        $picked = pick_role_player_index(
            $players,
            $history,
            [2],
            static fn(array $eligibleIds): int => $eligibleIds[0]
        );

        // Player 2 (target) is excluded, so FD must be 0 or 1.
        $this->assertContains($picked, [0, 1]);
    }

    public function test_all_active_players_served_role_false_until_everyone_seen(): void
    {
        $players = $this->makePlayers(0, 1, 2);
        $this->assertFalse(all_active_players_served_role($players, [0, 2]));
    }

    public function test_all_active_players_served_role_true_when_everyone_seen(): void
    {
        $players = $this->makePlayers(0, 1, 2);
        $this->assertTrue(all_active_players_served_role($players, [2, 1, 0]));
    }

    public function test_final_decider_cycle_stays_locked_before_target_cycle_completes(): void
    {
        $players = $this->makePlayers(0, 1, 2, 3);

        // Targets have not yet covered player 3.
        $projectedTargetHistory = [0, 1, 2];
        $this->assertFalse(all_active_players_served_role($players, $projectedTargetHistory));

        // Final decider should avoid repeats while target cycle is incomplete.
        // FD history includes 0 and 1; exclude target=2 -> eligible non-repeats should pick 3.
        $picked = pick_role_player_index(
            $players,
            [0, 1],
            [2],
            static fn(array $eligibleIds): int => $eligibleIds[0]
        );

        $this->assertSame(3, $picked);
    }

    public function test_final_decider_resolves_overlap_when_only_unserved_candidate_is_target(): void
    {
        $players = [
            new Player(
                id:           1,
                lobbyId:      1,
                name:         'Player1',
                sessionToken: str_repeat('a', 64),
                isHost:       true,
                turnOrder:    0,
                status:       'active',
                joinedAt:     '2024-01-01 00:00:00',
            ),
            new Player(
                id:           2,
                lobbyId:      1,
                name:         'Player2',
                sessionToken: str_repeat('b', 64),
                isHost:       false,
                turnOrder:    1,
                status:       'active',
                joinedAt:     '2024-01-01 00:00:00',
            ),
            new Player(
                id:           3,
                lobbyId:      1,
                name:         'Player3',
                sessionToken: str_repeat('c', 64),
                isHost:       false,
                turnOrder:    2,
                status:       'active',
                joinedAt:     '2024-01-01 00:00:00',
            ),
            new Player(
                id:           4,
                lobbyId:      1,
                name:         'Player4',
                sessionToken: str_repeat('d', 64),
                isHost:       false,
                turnOrder:    3,
                status:       'active',
                joinedAt:     '2024-01-01 00:00:00',
            ),
        ];

        // Players 1-3 have already served each role; only player 4 is unserved for both.
        $targetHistory = [1, 2, 3];
        $fdHistory = [1, 2, 3];

        $targetIndex = pick_role_player_index(
            $players,
            $targetHistory,
            [],
            static fn(array $eligibleIds): int => $eligibleIds[0]
        );
        $targetId = $players[$targetIndex]->id;
        $this->assertSame(4, $targetId);

        // Target cycle becomes complete once player 4 is projected.
        $projectedTargetHistory = [...$targetHistory, $targetId];
        $this->assertTrue(all_active_players_served_role($players, $projectedTargetHistory));

        // Because target cycle is complete, FD selection resets and must exclude target=4.
        $fdHistoryForSelection = [];
        $fdIndex = pick_role_player_index(
            $players,
            $fdHistoryForSelection,
            [$targetId],
            static fn(array $eligibleIds): int => $eligibleIds[0]
        );
        $fdId = $players[$fdIndex]->id;

        $this->assertContains($fdId, [1, 2, 3]);
        $this->assertNotSame($targetId, $fdId);
    }

    public function test_three_turn_sequence_matches_requested_host_target_decider_pattern(): void
    {
        $players = [
            new Player(
                id:           1,
                lobbyId:      1,
                name:         'Player1',
                sessionToken: str_repeat('a', 64),
                isHost:       true,
                turnOrder:    0,
                status:       'active',
                joinedAt:     '2024-01-01 00:00:00',
            ),
            new Player(
                id:           2,
                lobbyId:      1,
                name:         'Player2',
                sessionToken: str_repeat('b', 64),
                isHost:       false,
                turnOrder:    1,
                status:       'active',
                joinedAt:     '2024-01-01 00:00:00',
            ),
            new Player(
                id:           3,
                lobbyId:      1,
                name:         'Player3',
                sessionToken: str_repeat('c', 64),
                isHost:       false,
                turnOrder:    2,
                status:       'active',
                joinedAt:     '2024-01-01 00:00:00',
            ),
        ];

        $pickFirst = static fn(array $eligibleIds): int => $eligibleIds[0];

        // Start with Player 1 already present in FD history, which keeps turn 2 deterministic.
        $targetHistory = [];
        $fdHistory = [1];
        $turnAssignments = [];

        for ($turn = 1; $turn <= 3; $turn++) {
            $targetIndex = pick_role_player_index($players, $targetHistory, [], $pickFirst);
            $targetId = $players[$targetIndex]->id;

            $projectedTargetHistory = [...$targetHistory, $targetId];
            $targetCycleComplete = all_active_players_served_role($players, $projectedTargetHistory);
            $fdHistoryForSelection = $targetCycleComplete ? [] : $fdHistory;

            $fdIndex = pick_role_player_index($players, $fdHistoryForSelection, [$targetId], $pickFirst);
            $fdId = $players[$fdIndex]->id;

            $turnAssignments[$turn] = ['target' => $targetId, 'decider' => $fdId];

            $targetHistory[] = $targetId;
            $fdHistory[] = $fdId;
        }

        $this->assertTrue($players[0]->isHost);

        // Turn 1: P1 host+target, P2 decider, P3 none.
        $this->assertSame(['target' => 1, 'decider' => 2], $turnAssignments[1]);
        // Turn 2: P1 host only, P2 target, P3 decider.
        $this->assertSame(['target' => 2, 'decider' => 3], $turnAssignments[2]);
        // Turn 3: P1 host+decider, P2 none, P3 target.
        $this->assertSame(['target' => 3, 'decider' => 1], $turnAssignments[3]);
    }
}
