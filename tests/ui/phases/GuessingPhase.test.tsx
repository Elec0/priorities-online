import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { GuessingPhase } from '../../../priorities/assets/js/phases/GuessingPhase';
import { makeGameState, playerAlice, playerBob, playerCarol, cards } from '../fixtures';

vi.mock('../../../priorities/assets/js/api', () => ({
  updateGuess:  vi.fn(),
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

  beforeEach(() => mockLockInGuess.mockReset());

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
});
