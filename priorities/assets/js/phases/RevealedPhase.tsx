import { CardList } from '../components/CardList';
import type { GameState, ScoreResultState } from '../types';

interface Props {
  state: GameState;
}

export function RevealedPhase({ state }: Props) {
  const { round, target_player } = state;

  // Render cards in target_ranking order to show what the target chose.
  const targetOrder = round.target_ranking ?? round.card_ids;
  const orderedCards = targetOrder.map(id => round.cards.find(c => c.id === id)!);
  const results: ScoreResultState[] = round.result ?? [];

  const correctCount = results.filter(r => r.correct).length;

  return (
    <div className="phase revealed-phase">
      <p className="phase-label">
        Round {round.number} — {correctCount}/{results.length} correct!
      </p>
      <p className="revealed-subtitle">
        {target_player.name}'s actual ranking:
      </p>

      <CardList
        cards={orderedCards}
        draggable={false}
        results={results}
      />
    </div>
  );
}
