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

  it('shows final correct and guess order lists under the result message', () => {
    const state = makeGameState({
      game_status: 'players_win',
      round: {
        id: 1,
        number: 4,
        status: 'revealed',
        card_ids: [1, 2, 3],
        cards: [
          { id: 1, content: 'Pizza', category: 'food', emoji: '🍕', letter: 'P' },
          { id: 2, content: 'Rain', category: 'weather', emoji: '🌧', letter: 'R' },
          { id: 3, content: 'Ice cream', category: 'food', emoji: '🍦', letter: 'I' },
        ],
        group_ranking: [1, 2, 3],
        target_ranking: [3, 1, 2],
        result: [
          { card_id: 1, correct: true },
          { card_id: 2, correct: false },
          { card_id: 3, correct: true },
        ],
        ranking_deadline: null,
      },
    });

    render(<GameOverScreen state={state} />);

    const titles = Array.from(document.querySelectorAll('.revealed-col-label')).map(el => el.textContent);
    expect(titles).toEqual(['Correct Order', 'Final Guess Order']);

    const columns = document.querySelectorAll('.reveal-col');
    const correctItems = Array.from(columns[0].querySelectorAll('.card-content')).map(el => el.textContent?.trim());
    const guessItems = Array.from(columns[1].querySelectorAll('.card-content')).map(el => el.textContent?.trim());

    expect(correctItems).toEqual(['Ice cream', 'Pizza', 'Rain']);
    expect(guessItems).toEqual(['Pizza', 'Rain', 'Ice cream']);

    const correctColumnRows = columns[0].querySelectorAll('.card-item');
    expect(correctColumnRows[0]).toHaveClass('correct');
    expect(correctColumnRows[1]).toHaveClass('correct');
    expect(correctColumnRows[2]).toHaveClass('incorrect');
  });

  it('shows fallback text when final orders are unavailable', () => {
    const state = makeGameState({
      game_status: 'draw',
      round: {
        id: 1,
        number: 4,
        status: 'revealed',
        card_ids: [1, 2, 3],
        cards: [
          { id: 1, content: 'Pizza', category: 'food', emoji: '🍕', letter: 'P' },
          { id: 2, content: 'Rain', category: 'weather', emoji: '🌧', letter: 'R' },
          { id: 3, content: 'Ice cream', category: 'food', emoji: '🍦', letter: 'I' },
        ],
        group_ranking: null,
        target_ranking: undefined,
        result: undefined,
        ranking_deadline: null,
      },
    });

    render(<GameOverScreen state={state} />);
    expect(screen.getByText('No final correct order available.')).toBeInTheDocument();
    expect(screen.getByText('No final guess order available.')).toBeInTheDocument();
  });
});
