import { render, screen } from '@testing-library/react';
import { RevealedPhase } from '../../../priorities/assets/js/phases/RevealedPhase';
import { makeGameState, playerAlice, playerBob, cards, scores } from '../fixtures';

const revealedState = makeGameState({
  round: {
    id: 1, number: 2, status: 'revealed',
    card_ids: [1, 2, 3], cards,
    group_ranking: [1, 2, 3],
    target_ranking: [3, 1, 2],
    result: scores,
    ranking_deadline: null,
  },
  target_player: playerAlice,
  final_decider: playerBob,
});

describe('RevealedPhase', () => {
  it('shows round number', () => {
    render(<RevealedPhase state={revealedState} />);
    expect(screen.getByText(/Round 2/)).toBeInTheDocument();
  });

  it('shows correct count out of total', () => {
    // scores has 2 correct out of 3
    render(<RevealedPhase state={revealedState} />);
    expect(screen.getByText(/2\/3 correct/)).toBeInTheDocument();
  });

  it("shows the target player's name in the subtitle", () => {
    render(<RevealedPhase state={revealedState} />);
    expect(screen.getByText(/Alice's actual ranking/)).toBeInTheDocument();
  });

  it('renders cards in target_ranking order', () => {
    render(<RevealedPhase state={revealedState} />);
    // There are two columns; the first column is target ranking
    // target_ranking = [3, 1, 2] → Ice cream(3), Pizza(1), Rain(2)
    const cols = document.querySelectorAll('.reveal-col');
    const targetContents = Array.from(cols[0].querySelectorAll('.card-content')).map(el => el.textContent);
    expect(targetContents).toEqual(['Ice cream', 'Pizza', 'Rain']);
  });

  it('annotates cards with correct/incorrect classes', () => {
    render(<RevealedPhase state={revealedState} />);
    const items = Array.from(document.querySelectorAll('.card-item'));
    // After reordering: Ice cream (id=3, correct), Pizza (id=1, correct), Rain (id=2, incorrect)
    expect(items[0]).toHaveClass('correct');   // Ice cream
    expect(items[1]).toHaveClass('correct');   // Pizza
    expect(items[2]).toHaveClass('incorrect'); // Rain
  });

  it('shows 0/0 correct when no results yet', () => {
    const state = makeGameState({
      round: {
        id: 1, number: 1, status: 'revealed',
        card_ids: [1, 2, 3], cards,
        group_ranking: null,
        target_ranking: [1, 2, 3],
        ranking_deadline: null,
        result: [],
      },
      target_player: playerAlice,
      final_decider: playerBob,
    });
    render(<RevealedPhase state={state} />);
    expect(screen.getByText(/0\/0 correct/)).toBeInTheDocument();
  });

  it('falls back to card_ids order when target_ranking is absent', () => {
    const state = makeGameState({
      round: {
        id: 1, number: 1, status: 'revealed',
        card_ids: [1, 2, 3], cards,
        group_ranking: null,
        ranking_deadline: null,
        result: scores,
      },
      target_player: playerAlice,
      final_decider: playerBob,
    });
    render(<RevealedPhase state={state} />);
    // Two columns rendered; first column is the target (falls back to card_ids)
    const cols = document.querySelectorAll('.reveal-col');
    const targetContents = Array.from(cols[0].querySelectorAll('.card-content')).map(el => el.textContent);
    expect(targetContents).toEqual(['Pizza', 'Rain', 'Ice cream']);
  });
});
