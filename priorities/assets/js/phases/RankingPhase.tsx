import { useState } from 'react';
import { CardList } from '../components/CardList';
import { CountdownTimer } from '../components/CountdownTimer';
import { submitRanking } from '../api';
import type { GameState } from '../types';

interface Props {
  state: GameState;
  playerId: number;
}

export function RankingPhase({ state, playerId }: Props) {
  const { round, target_player } = state;
  const isTarget = playerId === target_player.id;

  const [orderedIds, setOrderedIds] = useState<number[]>(round.card_ids);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError]           = useState('');

  async function handleSubmit() {
    setError('');
    setSubmitting(true);
    try {
      await submitRanking(orderedIds);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to submit');
      setSubmitting(false);
    }
  }

  if (!isTarget) {
    return (
      <div className="phase ranking-phase">
        <p className="phase-label">
          🤫 <strong>{target_player.name}</strong> is secretly ranking their cards…
        </p>
        <CountdownTimer deadline={round.ranking_deadline} />
      </div>
    );
  }

  return (
    <div className="phase ranking-phase">
      <p className="phase-label">Rank these cards from <strong>love</strong> (top) to <strong>loathe</strong> (bottom).</p>
      <CountdownTimer deadline={round.ranking_deadline} />

      <CardList
        cards={round.cards}
        draggable
        onReorder={setOrderedIds}
      />

      {error && <p className="error-msg">{error}</p>}

      <button
        className="action-btn"
        onClick={handleSubmit}
        disabled={submitting}
      >
        {submitting ? 'Submitting…' : 'Submit My Ranking'}
      </button>
    </div>
  );
}
