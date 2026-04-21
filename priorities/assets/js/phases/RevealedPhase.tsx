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

  // Render cards in target_ranking order (left column).
  const targetOrder = round.target_ranking ?? round.card_ids;
  const targetCards = targetOrder.map(id => round.cards.find(c => c.id === id)!);
  const results: ScoreResultState[] = round.result ?? [];

  // Build a card_id → result map so both columns can share the same coloring.
  const resultByCardId = new Map<number, ScoreResultState>(results.map(r => [r.card_id, r]));
  const targetResults  = targetCards.map(c => resultByCardId.get(c.id))  as ScoreResultState[];

  // Right column: group's guess ordering.
  const groupOrder = round.group_ranking ?? round.card_ids;
  const groupCards   = groupOrder.map(id => round.cards.find(c => c.id === id)!);
  const groupResults = groupCards.map(c => resultByCardId.get(c.id)) as ScoreResultState[];

  const correctCount = results.filter(r => r.correct).length;

  return (
    <div className="phase revealed-phase">
      <p className="phase-label">
        Round {round.number} — {correctCount}/{results.length} correct!
      </p>
      <div className="reveal-cols">
        <div className="reveal-col">
          <p className="revealed-col-label">{target_player.name}'s actual ranking</p>
          <CardList cards={targetCards} draggable={false} results={targetResults} />
        </div>
        <div className="reveal-col">
          <p className="revealed-col-label">Group's guess</p>
          <CardList cards={groupCards} draggable={false} results={groupResults} />
        </div>
      </div>

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
