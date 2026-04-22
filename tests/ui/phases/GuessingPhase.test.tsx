import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { GuessingPhase } from '../../../priorities/assets/js/phases/GuessingPhase';
import { makeGameState, playerAlice, playerBob, playerCarol, cards } from '../fixtures';

vi.mock('../../../priorities/assets/js/api', () => ({
  updateGuess:  vi.fn().mockResolvedValue({ success: true }),
  lockInGuess:  vi.fn(),
}));

import * as api from '../../../priorities/assets/js/api';
const mockLockInGuess = vi.mocked(api.lockInGuess);

describe('GuessingPhase', () => {
  const guessingState = makeGameState({
    round: {
      id: 1, number: 1, status: 'guessing',
      card_ids: [1, 2, 3], cards,
      group_ranking: [1, 2, 3],
      ranking_deadline: null,
    },
    target_player: playerAlice,
    final_decider: playerBob,
  });

  beforeEach(() => {
    mockLockInGuess.mockReset();
    window.sessionStorage.clear();
  });

  it('shows waiting message to the target player', () => {
    render(<GuessingPhase state={guessingState} playerId={playerAlice.id} />);
    expect(screen.getByText(/Everyone is guessing your ranking, Alice/)).toBeInTheDocument();
  });

  it('shows ranking prompt to non-target players', () => {
    render(<GuessingPhase state={guessingState} playerId={playerCarol.id} />);
    expect(screen.getByText(/Arrange the cards to match Alice's secret ranking/)).toBeInTheDocument();
  });

  it('shows Lock In button only to the final decider (non-target)', () => {
    render(<GuessingPhase state={guessingState} playerId={playerBob.id} />);
    expect(screen.getByRole('button', { name: 'Lock In Guess' })).toBeInTheDocument();
  });

  it('does not show Lock In button to a regular guesser', () => {
    render(<GuessingPhase state={guessingState} playerId={playerCarol.id} />);
    expect(screen.queryByRole('button', { name: 'Lock In Guess' })).toBeNull();
  });

  it('does not show Lock In button to the target player', () => {
    // Alice is both target AND if she were final_decider, she still shouldn't see it
    const samePersonState = makeGameState({
      ...guessingState,
      final_decider: playerAlice,
    });
    render(<GuessingPhase state={samePersonState} playerId={playerAlice.id} />);
    expect(screen.queryByRole('button', { name: 'Lock In Guess' })).toBeNull();
  });

  it('shows the fd-hint to the final decider', () => {
    render(<GuessingPhase state={guessingState} playerId={playerBob.id} />);
    expect(screen.getByText(/You are the final decider/)).toBeInTheDocument();
  });

  it('calls lockInGuess when Lock In button is clicked', async () => {
    mockLockInGuess.mockResolvedValue({ success: true });
    const user = userEvent.setup();
    render(<GuessingPhase state={guessingState} playerId={playerBob.id} />);
    await user.click(screen.getByRole('button', { name: 'Lock In Guess' }));
    await waitFor(() => expect(mockLockInGuess).toHaveBeenCalledOnce());
  });

  it('falls back to card_ids when group_ranking is null', () => {
    const state = makeGameState({
      round: { ...guessingState.round, group_ranking: null },
      target_player: playerAlice,
      final_decider: playerBob,
    });
    render(<GuessingPhase state={state} playerId={playerCarol.id} />);
    // All cards should still render
    expect(screen.getByText('Pizza')).toBeInTheDocument();
    expect(screen.getByText('Rain')).toBeInTheDocument();
    expect(screen.getByText('Ice cream')).toBeInTheDocument();
  });

  it('keeps showing the target submitted order after transitioning to guessing', () => {
    window.sessionStorage.setItem('targetSubmittedOrder:1', JSON.stringify([3, 1, 2]));

    render(<GuessingPhase state={guessingState} playerId={playerAlice.id} />);

    const columns = document.querySelectorAll('.reveal-col');
    const submittedOrder = Array.from(columns[0].querySelectorAll('.card-content')).map(el => el.textContent?.trim());
    expect(submittedOrder).toEqual(['Ice cream', 'Pizza', 'Rain']);
  });

  it('shows target two columns and updates the group guess column from SSE state', () => {
    window.sessionStorage.setItem('targetSubmittedOrder:1', JSON.stringify([3, 1, 2]));

    const initialState = makeGameState({
      round: {
        id: 1, number: 1, status: 'guessing',
        card_ids: [1, 2, 3], cards,
        group_ranking: [1, 2, 3],
        ranking_deadline: null,
      },
      target_player: playerAlice,
      final_decider: playerBob,
    });

    const { rerender } = render(<GuessingPhase state={initialState} playerId={playerAlice.id} />);

    const labels = Array.from(document.querySelectorAll('.revealed-col-label')).map(el => el.textContent?.trim());
    expect(labels).toEqual(['Your Submitted Order', 'Current Group Guess']);

    const columns = document.querySelectorAll('.reveal-col');
    const submittedOrder = Array.from(columns[0].querySelectorAll('.card-content')).map(el => el.textContent?.trim());
    const initialGuess = Array.from(columns[1].querySelectorAll('.card-content')).map(el => el.textContent?.trim());

    expect(submittedOrder).toEqual(['Ice cream', 'Pizza', 'Rain']);
    expect(initialGuess).toEqual(['Pizza', 'Rain', 'Ice cream']);

    const updatedState = makeGameState({
      round: {
        id: 1, number: 1, status: 'guessing',
        card_ids: [1, 2, 3], cards,
        group_ranking: [2, 1, 3],
        ranking_deadline: null,
      },
      target_player: playerAlice,
      final_decider: playerBob,
    });

    rerender(<GuessingPhase state={updatedState} playerId={playerAlice.id} />);

    const updatedSubmittedOrder = Array.from(columns[0].querySelectorAll('.card-content')).map(el => el.textContent?.trim());
    const updatedGuess = Array.from(columns[1].querySelectorAll('.card-content')).map(el => el.textContent?.trim());

    expect(updatedSubmittedOrder).toEqual(['Ice cream', 'Pizza', 'Rain']);
    expect(updatedGuess).toEqual(['Rain', 'Pizza', 'Ice cream']);
  });
});
