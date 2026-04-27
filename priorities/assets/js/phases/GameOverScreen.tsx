import { useState } from 'react';
import { restartGame } from '../api';
import { LetterTiles } from '../components/LetterTiles';
import { CardList } from '../components/CardList';
import type { GameState } from '../types';

interface Props {
  state: GameState;
  playerId: number;
  lobbyId: number;
  devProfile: string;
}

export function GameOverScreen({ state, playerId, lobbyId, devProfile }: Props) {
  const { game_status, player_letters, game_letters, players, round } = state;
  const [restarting, setRestarting] = useState(false);

  const me = players.find(p => p.id === playerId);
  const isHost = me?.is_host ?? false;

  const message = {
    players_win: '🎉 Players win! You spelled PRIORITIES!',
    game_wins:   '😱 The game wins! Better luck next time.',
    draw:        '🤝 It\'s a draw — the deck ran out!',
    active:      '',
  }[game_status] ?? '';

  const cardsById = new Map(round.cards.map(card => [card.id, card] as const));
  const correctOrder = round.target_ranking ?? [];
  const guessOrder = round.group_ranking ?? [];
  const finalResults = round.result ?? [];
  const correctCards = correctOrder.map(id => cardsById.get(id)).filter((c): c is NonNullable<typeof c> => c !== undefined);
  const guessCards = guessOrder.map(id => cardsById.get(id)).filter((c): c is NonNullable<typeof c> => c !== undefined);

  function handleReturn() {
    window.location.href = 'index.php';
  }

  async function handleRestart() {
    setRestarting(true);
    try {
      await restartGame();
      const query = devProfile
        ? `?lobby_id=${lobbyId}&dev_profile=${encodeURIComponent(devProfile)}`
        : `?lobby_id=${lobbyId}`;
      window.location.href = `lobby.php${query}`;
    } catch (err) {
      setRestarting(false);
      alert(err instanceof Error ? err.message : 'Failed to restart game');
    }
  }

  return (
    <div className="game-over-screen">
      <h2 className="game-over-title">{message}</h2>

      <div className="reveal-cols" aria-label="Final round orders">
        <div className="reveal-col">
          <p className="revealed-col-label">Correct Order</p>
          {correctCards.length > 0 ? (
            <CardList cards={correctCards} draggable={false} results={finalResults} />
          ) : (
            <p className="waiting-msg">No final correct order available.</p>
          )}
        </div>

        <div className="reveal-col">
          <p className="revealed-col-label">Final Guess Order</p>
          {guessCards.length > 0 ? (
            <CardList cards={guessCards} draggable={false} results={finalResults} />
          ) : (
            <p className="waiting-msg">No final guess order available.</p>
          )}
        </div>
      </div>

      <div className="final-letters">
        <LetterTiles label="Players" letters={player_letters} />
        <LetterTiles label="Game"    letters={game_letters} />
      </div>

      {isHost ? (
        <button className="action-btn" onClick={handleRestart} disabled={restarting}>
          {restarting ? 'Restarting…' : 'Restart with Same People'}
        </button>
      ) : (
        <p className="waiting-msg">Waiting for the host to restart the game…</p>
      )}

      <button className="action-btn" onClick={handleReturn}>
        Return to Home
      </button>
    </div>
  );
}
