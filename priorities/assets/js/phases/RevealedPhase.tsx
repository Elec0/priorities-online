import { useState } from 'react';
import { CardList } from '../components/CardList';
import { nextRound } from '../api';
import type { GameState, ScoreResultState } from '../types';

interface Props {
  state: GameState;
  playerId: number;
}

export function RevealedPhase({ state, playerId }: Props) {
  const { round, target_player, final_decider, players } = state;
  const [advancing, setAdvancing] = useState(false);

  const me = players.find(p => p.id === playerId);
  const isHost = me?.is_host ?? false;
  const isFinalDecider = playerId === final_decider.id;
  const canContinue = isHost || isFinalDecider;

  async function handleContinue() {
    setAdvancing(true);
    try {
      await nextRound();
    } catch {
      setAdvancing(false);
    }
  }

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

      {canContinue ? (
        <button className="action-btn" onClick={handleContinue} disabled={advancing}>
          {advancing ? 'Starting…' : 'Continue to Next Round'}
        </button>
      ) : (
        <p className="waiting-msg">Waiting for the host or final decider to continue…</p>
      )}
    </div>
  );
}
