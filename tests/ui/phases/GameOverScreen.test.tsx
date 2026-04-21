import { render, screen } from '@testing-library/react';
import { GameOverScreen } from '../../../priorities/assets/js/phases/GameOverScreen';
import { makeGameState, emptyLetters } from '../fixtures';

describe('GameOverScreen', () => {
  it('shows players win message', () => {
    const state = makeGameState({ game_status: 'players_win' });
    render(<GameOverScreen state={state} />);
    expect(screen.getByText(/Players win/)).toBeInTheDocument();
  });

  it('shows game wins message', () => {
    const state = makeGameState({ game_status: 'game_wins' });
    render(<GameOverScreen state={state} />);
    expect(screen.getByText(/The game wins/)).toBeInTheDocument();
  });

  it('shows draw message', () => {
    const state = makeGameState({ game_status: 'draw' });
    render(<GameOverScreen state={state} />);
    expect(screen.getByText(/draw/i)).toBeInTheDocument();
  });

  it('renders two LetterTiles — one for Players, one for Game', () => {
    const state = makeGameState({ game_status: 'players_win' });
    render(<GameOverScreen state={state} />);
    expect(screen.getByText('Players')).toBeInTheDocument();
    expect(screen.getByText('Game')).toBeInTheDocument();
  });

  it('reflects player_letters in the Players tile set', () => {
    const state = makeGameState({
      game_status: 'players_win',
      player_letters: { ...emptyLetters, P: 1, R: 1 },
    });
    render(<GameOverScreen state={state} />);
    // Both tile groups exist; the Players group should have 2 filled tiles (P≥1, R≥1)
    const allFilled = document.querySelectorAll('.tile.filled');
    expect(allFilled.length).toBe(2);
  });

  it('reflects game_letters in the Game tile set', () => {
    const state = makeGameState({
      game_status: 'game_wins',
      game_letters: { ...emptyLetters, P: 1, R: 2, I: 3, O: 1, T: 1, E: 1, S: 1 },
    });
    render(<GameOverScreen state={state} />);
    const allFilled = document.querySelectorAll('.tile.filled');
    expect(allFilled.length).toBe(10); // all 10 PRIORITIES tiles
  });
});
