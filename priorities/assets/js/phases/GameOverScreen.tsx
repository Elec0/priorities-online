import { LetterTiles } from '../components/LetterTiles';
import type { GameState } from '../types';

interface Props {
  state: GameState;
}

export function GameOverScreen({ state }: Props) {
  const { game_status, player_letters, game_letters } = state;

  const message = {
    players_win: '🎉 Players win! You spelled PRIORITIES!',
    game_wins:   '😱 The game wins! Better luck next time.',
    draw:        '🤝 It\'s a draw — the deck ran out!',
    active:      '',
  }[game_status] ?? '';

  return (
    <div className="phase game-over-screen">
      <h2 className="game-over-title">{message}</h2>

      <div className="final-letters">
        <LetterTiles label="Players" letters={player_letters} />
        <LetterTiles label="Game"    letters={game_letters} />
      </div>
    </div>
  );
}
