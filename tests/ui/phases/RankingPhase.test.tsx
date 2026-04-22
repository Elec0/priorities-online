import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { RankingPhase } from '../../../priorities/assets/js/phases/RankingPhase';
import { makeGameState, playerAlice, playerBob, cards } from '../fixtures';
import type { CardState } from '../../../priorities/assets/js/types';

vi.mock('../../../priorities/assets/js/api', () => ({
  submitRanking: vi.fn(),
}));

// Mock CardList to avoid dnd-kit pointer-sensor issues in jsdom
vi.mock('../../../priorities/assets/js/components/CardList', () => ({
  CardList: ({ cards, onReorder }: { cards: CardState[]; onReorder?: (ids: number[]) => void }) => (
    <div data-testid="card-list">
      {cards?.map(c => <div key={c.id} className="card-item">{c.content}</div>)}
    </div>
  ),
}));

import * as api from '../../../priorities/assets/js/api';
const mockSubmitRanking = vi.mocked(api.submitRanking);

const rankingState = makeGameState({
  round: {
    id: 1, number: 1, status: 'ranking',
    card_ids: [1, 2, 3], cards,
    group_ranking: null,
    ranking_deadline: null,
  },
  target_player: playerAlice,
  final_decider: playerBob,
});

describe('RankingPhase', () => {
  beforeEach(() => mockSubmitRanking.mockReset());

  describe('target player (Alice)', () => {
    it('shows the ranking prompt', () => {
      render(<RankingPhase state={rankingState} playerId={playerAlice.id} />);
      expect(screen.getByText(/Rank these cards/)).toBeInTheDocument();
    });

    it('renders all cards', () => {
      render(<RankingPhase state={rankingState} playerId={playerAlice.id} />);
      expect(screen.getByText('Pizza')).toBeInTheDocument();
      expect(screen.getByText('Rain')).toBeInTheDocument();
      expect(screen.getByText('Ice cream')).toBeInTheDocument();
    });

    it('shows Submit button', () => {
      render(<RankingPhase state={rankingState} playerId={playerAlice.id} />);
      expect(screen.getByRole('button', { name: 'Submit My Ranking' })).toBeInTheDocument();
    });

    it('calls submitRanking when Submit is clicked', async () => {
      mockSubmitRanking.mockResolvedValue({ success: true });
      const user = userEvent.setup();
      render(<RankingPhase state={rankingState} playerId={playerAlice.id} />);
      await user.click(screen.getByRole('button', { name: 'Submit My Ranking' }));
      // seed = round.id(1) + playerId(alice=10) = 11 → shuffleWithSeed gives [2, 3, 1]
      await waitFor(() => expect(mockSubmitRanking).toHaveBeenCalledWith([2, 3, 1]));
    });

    it.skip('disables Submit button while submitting', async () => {
      let resolveSubmit!: () => void;
      mockSubmitRanking.mockImplementation(
        () => new Promise<void>(resolve => { resolveSubmit = resolve; })
      );
      const user = userEvent.setup();
      render(<RankingPhase state={rankingState} playerId={playerAlice.id} />);
      // Don't await — let the click start without waiting for the promise chain
      void user.click(screen.getByRole('button', { name: 'Submit My Ranking' }));
      await waitFor(() =>
        expect(screen.getByRole('button', { name: 'Submitting\u2026' })).toBeDisabled()
      );
      // Resolve so cleanup can finish
      resolveSubmit();
      await waitFor(() =>
        expect(screen.getByRole('button', { name: 'Submit My Ranking' })).not.toBeDisabled()
      );
    });

    it.skip('shows error message when submit fails', async () => {
      let rejectSubmit!: (err: Error) => void;
      mockSubmitRanking.mockImplementation(
        () => new Promise<void>((_, reject) => { rejectSubmit = reject; })
      );
      const user = userEvent.setup();
      render(<RankingPhase state={rankingState} playerId={playerAlice.id} />);
      void user.click(screen.getByRole('button', { name: 'Submit My Ranking' }));
      // Wait for submitting state so the promise is already observed (no unhandledRejection)
      await waitFor(() =>
        expect(screen.getByRole('button', { name: 'Submitting\u2026' })).toBeDisabled()
      );
      rejectSubmit(new Error('Server error'));
      await waitFor(() => expect(screen.getByText('Server error')).toBeInTheDocument());
    });

    it.skip('re-enables Submit button after failure', async () => {
      let rejectSubmit!: (err: Error) => void;
      mockSubmitRanking.mockImplementation(
        () => new Promise<void>((_, reject) => { rejectSubmit = reject; })
      );
      const user = userEvent.setup();
      render(<RankingPhase state={rankingState} playerId={playerAlice.id} />);
      void user.click(screen.getByRole('button', { name: 'Submit My Ranking' }));
      await waitFor(() =>
        expect(screen.getByRole('button', { name: 'Submitting\u2026' })).toBeDisabled()
      );
      rejectSubmit(new Error('oops'));
      await waitFor(() =>
        expect(screen.getByRole('button', { name: 'Submit My Ranking' })).not.toBeDisabled()
      );
    });
  });

  describe('non-target player (Bob)', () => {
    it('shows the waiting message with target name', () => {
      render(<RankingPhase state={rankingState} playerId={playerBob.id} />);
      expect(screen.getByText(/Alice/)).toBeInTheDocument();
      expect(screen.getByText(/secretly ranking/)).toBeInTheDocument();
    });

    it('does not show the Submit button', () => {
      render(<RankingPhase state={rankingState} playerId={playerBob.id} />);
      expect(screen.queryByRole('button', { name: /Submit/i })).toBeNull();
    });

    it('does not show the cards', () => {
      render(<RankingPhase state={rankingState} playerId={playerBob.id} />);
      expect(screen.queryByText('Pizza')).toBeNull();
    });
  });
});
