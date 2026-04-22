<?php
declare(strict_types=1);

namespace Tests;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../priorities/includes/db_access.php';

class DbAccessTest extends TestCase
{
    private function expectExecute(PDOStatement $stmt, array $params): void
    {
        $execute = $stmt->expects($this->once())
            ->method('execute');

        if ($params === []) {
            $execute->with($this->callback(static fn(mixed $arg): bool => $arg === null || $arg === []));
            return;
        }

        $execute->with($params);
    }

    private function mockPdo(PDOStatement $stmt, string $sqlNeedle, ?string $lastInsertId = null): PDO
    {
        $db = $this->getMockBuilder(PDO::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['prepare', 'lastInsertId'])
            ->getMock();

        $db->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains($sqlNeedle))
            ->willReturn($stmt);

        if ($lastInsertId !== null) {
            $db->expects($this->once())
                ->method('lastInsertId')
                ->willReturn($lastInsertId);
        }

        return $db;
    }

    private function stmtExecuteOnly(array $params): PDOStatement
    {
        $stmt = $this->getMockBuilder(PDOStatement::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['execute'])
            ->getMock();

        $this->expectExecute($stmt, $params);

        return $stmt;
    }

    private function stmtExecuteAndFetch(array $params, array|false $result): PDOStatement
    {
        $stmt = $this->getMockBuilder(PDOStatement::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['execute', 'fetch'])
            ->getMock();

        $this->expectExecute($stmt, $params);

        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn($result);

        return $stmt;
    }

    private function stmtExecuteAndFetchAll(array $params, array $result): PDOStatement
    {
        $stmt = $this->getMockBuilder(PDOStatement::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['execute', 'fetchAll'])
            ->getMock();

        $this->expectExecute($stmt, $params);

        $stmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn($result);

        return $stmt;
    }

    private function stmtExecuteAndFetchColumn(array $params, mixed $result): PDOStatement
    {
        $stmt = $this->getMockBuilder(PDOStatement::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['execute', 'fetchColumn'])
            ->getMock();

        $this->expectExecute($stmt, $params);

        $stmt->expects($this->once())
            ->method('fetchColumn')
            ->willReturn($result);

        return $stmt;
    }

    private function stmtExecuteAndRowCount(array $params, int $rowCount): PDOStatement
    {
        $stmt = $this->getMockBuilder(PDOStatement::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['execute', 'rowCount'])
            ->getMock();

        $this->expectExecute($stmt, $params);

        $stmt->expects($this->once())
            ->method('rowCount')
            ->willReturn($rowCount);

        return $stmt;
    }

    public function test_lobby_helpers(): void
    {
        $insertStmt = $this->stmtExecuteOnly([
            ':code' => 'ABC123',
            ':token' => str_repeat('t', 64),
            ':timer_enabled' => 1,
            ':timer_seconds' => 45,
        ]);
        $insertDb = $this->mockPdo($insertStmt, 'INSERT INTO lobbies', '10');
        $this->assertSame(10, dbx_insert_lobby($insertDb, 'ABC123', str_repeat('t', 64), true, 45));

        $existsStmt = $this->stmtExecuteAndFetchColumn([':code' => 'ABC123'], 1);
        $existsDb = $this->mockPdo($existsStmt, 'SELECT COUNT(*) FROM lobbies');
        $this->assertTrue(dbx_lobby_code_exists($existsDb, 'ABC123'));

        $waitingStmt = $this->stmtExecuteAndFetch([':code' => 'ABC123'], ['id' => 7]);
        $waitingDb = $this->mockPdo($waitingStmt, 'SELECT id FROM lobbies');
        $this->assertSame(['id' => 7], dbx_find_waiting_lobby_by_code($waitingDb, 'ABC123'));

        $setStatusStmt = $this->stmtExecuteOnly([':status' => 'playing', ':id' => 9]);
        $setStatusDb = $this->mockPdo($setStatusStmt, 'UPDATE lobbies SET status');
        dbx_set_lobby_status($setStatusDb, 9, 'playing');

        $timerStmt = $this->stmtExecuteAndFetch([':id' => 9], ['timer_enabled' => 1, 'timer_seconds' => 75]);
        $timerDb = $this->mockPdo($timerStmt, 'SELECT timer_enabled, timer_seconds');
        $timer = dbx_lobby_timer_settings($timerDb, 9);
        $this->assertTrue($timer['timer_enabled']);
        $this->assertSame(75, $timer['timer_seconds']);

        $lobbyStmt = $this->stmtExecuteAndFetch([':id' => 9], ['id' => 9, 'status' => 'waiting']);
        $lobbyDb = $this->mockPdo($lobbyStmt, 'SELECT * FROM lobbies');
        $this->assertSame(['id' => 9, 'status' => 'waiting'], dbx_fetch_lobby_by_id($lobbyDb, 9));

        $cleanupStmt = $this->stmtExecuteOnly([]);
        $cleanupDb = $this->mockPdo($cleanupStmt, 'DELETE FROM lobbies WHERE updated_at < DATE_SUB');
        dbx_delete_stale_lobbies($cleanupDb);
    }

    public function test_player_helpers(): void
    {
        $insertStmt = $this->stmtExecuteOnly([
            ':lobby_id' => 5,
            ':name' => 'Host',
            ':token' => str_repeat('1', 64),
            ':is_host' => 1,
            ':turn_order' => 0,
        ]);
        $insertDb = $this->mockPdo($insertStmt, 'INSERT INTO players', '22');
        $this->assertSame(22, dbx_insert_player($insertDb, 5, 'Host', str_repeat('1', 64), true, 0));

        $countNameStmt = $this->stmtExecuteAndFetchColumn([':lobby_id' => 5, ':name' => 'Host'], 1);
        $countNameDb = $this->mockPdo($countNameStmt, 'SELECT COUNT(*) FROM players WHERE lobby_id');
        $this->assertSame(1, dbx_count_active_players_with_name($countNameDb, 5, 'Host'));

        $countStmt = $this->stmtExecuteAndFetchColumn([':lobby_id' => 5], 3);
        $countDb = $this->mockPdo($countStmt, 'SELECT COUNT(*) FROM players');
        $this->assertSame(3, dbx_count_active_players($countDb, 5));

        $nextStmt = $this->stmtExecuteAndFetchColumn([':lobby_id' => 5], 4);
        $nextDb = $this->mockPdo($nextStmt, 'SELECT COALESCE(MAX(turn_order), -1) + 1');
        $this->assertSame(4, dbx_next_turn_order($nextDb, 5));

        $inLobbyStmt = $this->stmtExecuteAndFetch([':id' => 9, ':lobby_id' => 5], ['id' => 9, 'name' => 'Alex']);
        $inLobbyDb = $this->mockPdo($inLobbyStmt, 'SELECT id, name FROM players');
        $this->assertSame(['id' => 9, 'name' => 'Alex'], dbx_find_active_player_in_lobby($inLobbyDb, 9, 5));

        $kickedStmt = $this->stmtExecuteOnly([':id' => 9]);
        $kickedDb = $this->mockPdo($kickedStmt, "UPDATE players SET status = 'kicked'");
        dbx_mark_player_kicked($kickedDb, 9);

        $playerStmt = $this->stmtExecuteAndFetch([':id' => 9], ['id' => 9, 'name' => 'Alex']);
        $playerDb = $this->mockPdo($playerStmt, 'SELECT * FROM players WHERE id');
        $this->assertSame(['id' => 9, 'name' => 'Alex'], dbx_fetch_player_by_id($playerDb, 9));

        $tokenStmt = $this->stmtExecuteAndFetch([':token' => str_repeat('2', 64)], ['id' => 2, 'name' => 'Host']);
        $tokenDb = $this->mockPdo($tokenStmt, 'WHERE session_token = :token');
        $this->assertSame(['id' => 2, 'name' => 'Host'], dbx_find_active_player_by_token($tokenDb, str_repeat('2', 64)));

        $activeStmt = $this->stmtExecuteAndFetchAll([':lobby_id' => 5], [['id' => 1], ['id' => 2]]);
        $activeDb = $this->mockPdo($activeStmt, 'ORDER BY turn_order ASC');
        $this->assertCount(2, dbx_fetch_active_players_by_lobby($activeDb, 5));
    }

    public function test_card_helpers(): void
    {
        $allStmt = $this->stmtExecuteAndFetchAll([], [['id' => '1'], ['id' => '2']]);
        $allDb = $this->mockPdo($allStmt, 'SELECT id FROM cards');
        $this->assertSame([1, 2], dbx_all_card_ids($allDb));

        $cardsStmt = $this->stmtExecuteAndFetchAll([3, 7], [['id' => 3], ['id' => 7]]);
        $cardsDb = $this->mockPdo($cardsStmt, 'SELECT * FROM cards WHERE id IN');
        $this->assertCount(2, dbx_fetch_cards_by_ids($cardsDb, [3, 7]));

        $lettersStmt = $this->stmtExecuteAndFetchAll([3, 7], [['letter' => 'P'], ['letter' => 'R']]);
        $lettersDb = $this->mockPdo($lettersStmt, 'SELECT letter FROM cards WHERE id IN');
        $this->assertSame(['P', 'R'], dbx_fetch_card_letters_by_ids($lettersDb, [3, 7]));

        $emptyCardsDb = $this->getMockBuilder(PDO::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['prepare'])
            ->getMock();
        $emptyCardsDb->expects($this->never())->method('prepare');
        $this->assertSame([], dbx_fetch_cards_by_ids($emptyCardsDb, []));
        $this->assertSame([], dbx_fetch_card_letters_by_ids($emptyCardsDb, []));
    }

    public function test_chat_helpers(): void
    {
        $insertStmt = $this->stmtExecuteOnly([':lobby_id' => 8, ':player_id' => 2, ':message' => 'hello']);
        $insertDb = $this->mockPdo($insertStmt, 'INSERT INTO chat_messages');
        dbx_insert_chat_message($insertDb, 8, 2, 'hello');

        $recentRows = [
            ['id' => 2, 'message' => 'second'],
            ['id' => 1, 'message' => 'first'],
        ];
        $recentStmt = $this->stmtExecuteAndFetchAll([':lobby_id' => 8], $recentRows);
        $recentDb = $this->mockPdo($recentStmt, 'FROM chat_messages cm');
        $this->assertSame(array_reverse($recentRows), dbx_fetch_recent_chat($recentDb, 8));

        $systemStmt = $this->stmtExecuteOnly([':lobby_id' => 8, ':message' => 'system']);
        $systemDb = $this->mockPdo($systemStmt, 'VALUES (:lobby_id, NULL, :message)');
        dbx_insert_system_chat_message($systemDb, 8, 'system');
    }

    public function test_game_helpers(): void
    {
        $idStmt = $this->stmtExecuteAndFetch([':id' => 4], ['id' => '12']);
        $idDb = $this->mockPdo($idStmt, 'SELECT id FROM games WHERE lobby_id');
        $this->assertSame(12, dbx_get_game_id_by_lobby($idDb, 4));

        $countStmt = $this->stmtExecuteAndFetchColumn([':lobby_id' => 4], 1);
        $countDb = $this->mockPdo($countStmt, 'SELECT COUNT(*) FROM games');
        $this->assertSame(1, dbx_count_games_for_lobby($countDb, 4));

        $insertStmt = $this->stmtExecuteOnly([
            ':lobby_id' => 4,
            ':pl' => '{}',
            ':gl' => '{}',
            ':deck' => '[1,2,3]',
        ]);
        $insertDb = $this->mockPdo($insertStmt, 'INSERT INTO games', '99');
        $this->assertSame(99, dbx_insert_game($insertDb, 4, '{}', '{}', '[1,2,3]'));

        $fetchByIdStmt = $this->stmtExecuteAndFetch([':id' => 99], ['id' => 99, 'status' => 'active']);
        $fetchByIdDb = $this->mockPdo($fetchByIdStmt, 'SELECT * FROM games WHERE id');
        $this->assertSame(['id' => 99, 'status' => 'active'], dbx_fetch_game_by_id($fetchByIdDb, 99));

        $drawStmt = $this->stmtExecuteOnly([':id' => 99]);
        $drawDb = $this->mockPdo($drawStmt, "UPDATE games SET status = 'draw'");
        dbx_set_game_draw($drawDb, 99);

        $lettersStmt = $this->stmtExecuteOnly([
            ':pl' => '{"P":1}',
            ':gl' => '{"P":0}',
            ':status' => 'players_win',
            ':id' => 99,
        ]);
        $lettersDb = $this->mockPdo($lettersStmt, 'UPDATE games SET player_letters = :pl');
        dbx_update_game_letters_and_status($lettersDb, 99, '{"P":1}', '{"P":0}', 'players_win');

        $verStmt = $this->stmtExecuteAndFetch([':id' => 4], ['state_version' => 7]);
        $verDb = $this->mockPdo($verStmt, 'SELECT state_version FROM games');
        $this->assertSame(7, dbx_fetch_latest_state_version_by_lobby($verDb, 4));

        $latestStmt = $this->stmtExecuteAndFetch([':id' => 4], ['id' => 5]);
        $latestDb = $this->mockPdo($latestStmt, 'SELECT * FROM games WHERE lobby_id = :id ORDER BY id DESC');
        $this->assertSame(['id' => 5], dbx_fetch_latest_game_by_lobby($latestDb, 4));

        $deckStmt = $this->stmtExecuteOnly([':deck' => '[9]', ':id' => 99]);
        $deckDb = $this->mockPdo($deckStmt, 'UPDATE games SET deck_order = :deck');
        dbx_update_game_deck($deckDb, 99, '[9]');

        $idStatusStmt = $this->stmtExecuteAndFetch([':lobby_id' => 4], ['id' => 99, 'status' => 'active']);
        $idStatusDb = $this->mockPdo($idStatusStmt, 'SELECT id, status FROM games');
        $this->assertSame(['id' => 99, 'status' => 'active'], dbx_fetch_game_id_and_status_by_lobby($idStatusDb, 4));

        $deleteStmt = $this->stmtExecuteOnly([':id' => 99]);
        $deleteDb = $this->mockPdo($deleteStmt, 'DELETE FROM games WHERE id = :id');
        dbx_delete_game_by_id($deleteDb, 99);

        $incStmt = $this->stmtExecuteOnly([':id' => 99]);
        $incDb = $this->mockPdo($incStmt, 'SET state_version = state_version + 1');
        dbx_increment_state_version($incDb, 99);

        $nextRoundStmt = $this->stmtExecuteOnly([
            ':round_number' => 3,
            ':target_index' => 2,
            ':fd_index' => 1,
            ':deck_order' => '[4,5]',
            ':id' => 99,
        ]);
        $nextRoundDb = $this->mockPdo($nextRoundStmt, 'SET current_round        = :round_number');
        dbx_update_game_for_next_round($nextRoundDb, 99, 3, 2, 1, '[4,5]');
    }

    public function test_round_helpers(): void
    {
        $insertStmt = $this->stmtExecuteOnly([
            ':game_id' => 11,
            ':round_number' => 2,
            ':target_id' => 4,
            ':fd_id' => 5,
            ':card_ids' => '[1,2,3,4,5]',
        ]);
        $insertDb = $this->mockPdo($insertStmt, 'INSERT INTO rounds');
        dbx_insert_round($insertDb, 11, 2, 4, 5, '[1,2,3,4,5]', true, 60);

        $byStatusStmt = $this->stmtExecuteAndFetch([':lobby_id' => 1, ':status' => 'ranking'], ['id' => 8]);
        $byStatusDb = $this->mockPdo($byStatusStmt, 'SELECT r.* FROM rounds r');
        $this->assertSame(['id' => 8], dbx_fetch_round_by_status_for_lobby($byStatusDb, 1, 'ranking'));

        $toGuessStmt = $this->stmtExecuteOnly([':ranking' => '[1,2,3]', ':id' => 8]);
        $toGuessDb = $this->mockPdo($toGuessStmt, 'SET target_ranking = :ranking, status =');
        dbx_update_round_target_ranking_to_guessing($toGuessDb, 8, '[1,2,3]');

        $ifNullStmt = $this->stmtExecuteOnly([':gr' => '[1,2,3]', ':id' => 8]);
        $ifNullDb = $this->mockPdo($ifNullStmt, 'group_ranking IS NULL');
        dbx_set_round_group_ranking_if_null($ifNullDb, 8, '[1,2,3]');

        $groupStmt = $this->stmtExecuteOnly([':gr' => '[3,2,1]', ':id' => 8]);
        $groupDb = $this->mockPdo($groupStmt, 'UPDATE rounds SET group_ranking = :gr');
        dbx_update_round_group_ranking($groupDb, 8, '[3,2,1]');

        $revealedStmt = $this->stmtExecuteOnly([':result' => '[{"correct":true}]', ':id' => 8]);
        $revealedDb = $this->mockPdo($revealedStmt, "SET result = :result, status = 'revealed'");
        dbx_update_round_result_revealed($revealedDb, 8, '[{"correct":true}]');

        $prioStmt = $this->stmtExecuteAndFetch([':game_id' => 11], ['id' => 8, 'status' => 'ranking']);
        $prioDb = $this->mockPdo($prioStmt, 'CASE status WHEN');
        $this->assertSame(['id' => 8, 'status' => 'ranking'], dbx_fetch_prioritized_round_for_game($prioDb, 11));

        $timeoutStmt = $this->stmtExecuteAndFetch([':lobby_id' => 1], ['id' => 8]);
        $timeoutDb = $this->mockPdo($timeoutStmt, 'AND r.ranking_deadline < NOW()');
        $this->assertSame(['id' => 8], dbx_fetch_timed_out_ranking_round_for_lobby($timeoutDb, 1));

        $claimStmt = $this->stmtExecuteAndRowCount([':id' => 8], 1);
        $claimDb = $this->mockPdo($claimStmt, "SET status = 'skipped' WHERE id = :id AND status = 'ranking'");
        $this->assertSame(1, dbx_claim_round_skip($claimDb, 8));

        $skipStmt = $this->stmtExecuteOnly([':id' => 8]);
        $skipDb = $this->mockPdo($skipStmt, "SET status = 'skipped' WHERE id = :id");
        dbx_mark_round_skipped($skipDb, 8);
    }
}
