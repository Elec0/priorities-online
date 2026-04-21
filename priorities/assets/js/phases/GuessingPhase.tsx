import { useRef, useCallback, useEffect, useMemo } from 'react';
import { CardList } from '../components/CardList';
import { updateGuess, lockInGuess } from '../api';
import { shuffleWithSeed } from '../utils/shuffle';
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
  const initRef = useRef(false);

  // Default shuffled order for initial display
  // - Target player: unique shuffle (seed = round.id + playerId)
  // - All guessers: same shuffle (seed = round.id only) so they can discuss
  const defaultShuffledCards = useMemo(() => {
    const seed = isTarget ? (round.id + playerId) : round.id;
    return shuffleWithSeed([...round.cards], seed);
  }, [round.id, round.cards, isTarget, playerId]);

  // Display cards: use group_ranking if set (collaborative order), otherwise use default shuffle
  const displayCards = useMemo(() => {
    if (isTarget) {
      // Target player just waits, no dragging
      return defaultShuffledCards;
    }
    
    // For guessers: show the collaboratively agreed order if it exists
    if (round.group_ranking) {
      return round.group_ranking.map(id => round.cards.find(c => c.id === id)!);
    }
    
    // Otherwise show the default shuffled order
    return defaultShuffledCards;
  }, [isTarget, round.group_ranking, round.cards, defaultShuffledCards]);

  // Initialize group_ranking with the shuffled order this player sees (if not already set and it's a non-target player)
  useEffect(() => {
    if (initRef.current || isTarget || round.group_ranking !== null) {
      return; // Already initialized or target player
    }
    initRef.current = true;
    
    // Submit the initial shuffled display order as the default group guess
    const initialOrder = defaultShuffledCards.map(c => c.id);
    updateGuess(initialOrder).catch(() => {/* ignore errors on initial setup */});
  }, [isTarget, round.group_ranking, defaultShuffledCards]);

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
        cards={displayCards}
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
