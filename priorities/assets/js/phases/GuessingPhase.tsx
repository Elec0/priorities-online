import { useRef, useCallback } from 'react';
import { CardList } from '../components/CardList';
import { updateGuess, lockInGuess } from '../api';
import type { GameState } from '../types';

interface Props {
  state: GameState;
  playerId: number;
}

export function GuessingPhase({ state, playerId }: Props) {
  const { round, target_player, final_decider } = state;
  const isTarget     = playerId === target_player.id;
  const isFinalDecider = playerId === final_decider.id;

  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Provide the current group_ranking order for the card list.
  // Fall back to dealt order if group_ranking not yet set.
  const currentOrder = round.group_ranking ?? round.card_ids;
  const orderedCards = currentOrder.map(id => round.cards.find(c => c.id === id)!);

  const handleReorder = useCallback((orderedIds: number[]) => {
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => {
      updateGuess(orderedIds).catch(() => {/* ignore transient errors */});
    }, 400);
  }, []);

  async function handleLockIn() {
    try {
      await lockInGuess();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Failed to lock in');
    }
  }

  return (
    <div className="phase guessing-phase">
      <p className="phase-label">
        {isTarget
          ? `Everyone is guessing your ranking, ${target_player.name}…`
          : `Arrange the cards to match ${target_player.name}'s secret ranking.`}
      </p>

      <CardList
        cards={orderedCards}
        draggable={!isTarget}
        onReorder={!isTarget ? handleReorder : undefined}
      />

      {isFinalDecider && !isTarget && (
        <button className="action-btn lock-btn" onClick={handleLockIn}>
          Lock In Guess
        </button>
      )}

      {isFinalDecider && !isTarget && (
        <p className="fd-hint">You are the final decider — move the cards and lock in when ready.</p>
      )}
    </div>
  );
}
