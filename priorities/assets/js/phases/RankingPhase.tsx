import { useState, useMemo, useEffect } from 'react';
import { CardList } from '../components/CardList';
import { CountdownTimer } from '../components/CountdownTimer';
import { submitRanking } from '../api';
import { shuffleWithSeed } from '../utils/shuffle';
import type { GameState } from '../types';

interface Props {
  state: GameState;
  playerId: number;
}

const WAITING_GIF_PATHS = [
  'assets/images/1.gif',
  'assets/images/2.gif',
  'assets/images/3.gif',
  'assets/images/4.gif',
  'assets/images/5.gif',
  'assets/images/6.gif',
];

export function RankingPhase({ state, playerId }: Props) {
  const { round, target_player } = state;
  const isTarget = playerId === target_player.id;
  const submittedOrderStorageKey = `targetSubmittedOrder:${round.id}`;
  const waitingGif = useMemo(() => {
    const idx = Math.floor(Math.random() * WAITING_GIF_PATHS.length);
    return WAITING_GIF_PATHS[idx] ?? WAITING_GIF_PATHS[0];
  }, [round.id]);

  // Shuffle the card display order uniquely per player using seeded RNG
  // This ensures each player sees cards in a different visual order,
  // even if they all start with the same card_ids.
  const shuffledCards = useMemo(() => {
    const seed = round.id + playerId;
    return shuffleWithSeed([...round.cards], seed);
  }, [round.id, round.cards, playerId]);

  // Initialize with the shuffled order so initial submission matches the visual order.
  const initialIds = useMemo(() => shuffledCards.map(c => c.id), [shuffledCards]);

  const [orderedIds, setOrderedIds] = useState<number[]>(initialIds);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError]           = useState('');

  // Reset order only when the round changes.
  // Same-round SSE refreshes can send equivalent card arrays with new references;
  // do not clobber in-progress drag order on those updates.
  useEffect(() => {
    setOrderedIds(initialIds);
    setSubmitting(false);
    setError('');
  }, [round.id]);

  // Render in the current local order so what the target sees is what gets submitted.
  const displayCards = useMemo(() => {
    if (orderedIds.length !== shuffledCards.length) {
      return shuffledCards;
    }

    const byId = new Map(shuffledCards.map(c => [c.id, c] as const));
    return orderedIds
      .map(id => byId.get(id))
      .filter((c): c is NonNullable<typeof c> => c !== undefined);
  }, [orderedIds, shuffledCards]);

  async function handleSubmit() {
    setError('');
    setSubmitting(true);
    try {
      await submitRanking(orderedIds);
      window.sessionStorage.setItem(submittedOrderStorageKey, JSON.stringify(orderedIds));
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
        <img
          className="waiting-gif"
          src={waitingGif}
          alt="Waiting animation while the target ranks cards"
        />
        <CountdownTimer deadline={round.ranking_deadline} />
      </div>
    );
  }

  return (
    <div className="phase ranking-phase">
      <p className="phase-label">Rank these cards from <strong>love</strong> (top) to <strong>loathe</strong> (bottom).</p>
      <CountdownTimer deadline={round.ranking_deadline} />

      <CardList
        cards={displayCards}
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
