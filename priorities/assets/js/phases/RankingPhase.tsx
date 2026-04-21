import { useState, useMemo } from 'react';
import { CardList } from '../components/CardList';
import { CountdownTimer } from '../components/CountdownTimer';
import { submitRanking } from '../api';
import { shuffleWithSeed } from '../utils/shuffle';
import type { GameState } from '../types';

interface Props {
  state: GameState;
  playerId: number;
}

export function RankingPhase({ state, playerId }: Props) {
  const { round, target_player } = state;
  const isTarget = playerId === target_player.id;

  // Shuffle the card display order uniquely per player using seeded RNG
  // This ensures each player sees cards in a different visual order,
  // even if they all start with the same card_ids.
  const shuffledCards = useMemo(() => {
    const seed = round.id + playerId;
    return shuffleWithSeed([...round.cards], seed);
  }, [round.id, round.cards, playerId]);

  // Initialize with the shuffled order so initial submission matches the visual order
  const initialIds = useMemo(() => {
    return shuffledCards.map(c => c.id);
  }, [shuffledCards]);

  const [orderedIds, setOrderedIds] = useState<number[]>(initialIds);
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
        cards={shuffledCards}
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
