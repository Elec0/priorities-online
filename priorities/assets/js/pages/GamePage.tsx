import { LetterTiles } from '../components/LetterTiles';
import { ChatPanel } from '../components/ChatPanel';
import { RankingPhase } from '../phases/RankingPhase';
import { GuessingPhase } from '../phases/GuessingPhase';
import { RevealedPhase } from '../phases/RevealedPhase';
import { GameOverScreen } from '../phases/GameOverScreen';
import { useSSE } from '../hooks/useSSE';
import type { GameState } from '../types';

function getRootData() {
  const el = document.getElementById('root')!;
  return {
    lobbyId:  parseInt(el.dataset.lobbyId ?? '0', 10),
    playerId: parseInt(el.dataset.playerId ?? '0', 10),
  };
}

export function GamePage() {
  const { lobbyId, playerId } = getRootData();
  const { state } = useSSE(lobbyId, 0);

  if (!state || state.lobby_status !== 'playing') {
    return <div className="loading">Connecting…</div>;
  }

  const gameState = state as GameState;
  const { game_status, round, player_letters, game_letters, players, chat } = gameState;

  function renderPhase() {
    if (game_status !== 'active') {
      return <GameOverScreen state={gameState} />;
    }
    switch (round.status) {
      case 'ranking':  return <RankingPhase  state={gameState} playerId={playerId} />;
      case 'guessing': return <GuessingPhase state={gameState} playerId={playerId} />;
      case 'revealed': return <RevealedPhase state={gameState} playerId={playerId} />;
      case 'skipped':  return <div className="phase skipped-phase">Round skipped. Starting next round…</div>;
    }
  }

  return (
    <div className="game-page">
      <header className="game-header">
        <LetterTiles label="Players" letters={player_letters} />
        <LetterTiles label="Game"    letters={game_letters} />
        <button
          className="dev-dump-btn"
          onClick={() => { console.log('[DEV] gameState', gameState); }}
        >
          DEV: Dump State
        </button>
      </header>

      <div className="game-layout">
        <main className="game-main">
          <div className="round-info">
            Round {round.number}
            {' · '}
            <span className="target-name">{gameState.target_player.name}</span> is the target
          </div>

          {renderPhase()}
        </main>

        <aside className="game-sidebar">
          <div className="player-list-section">
            <h3>Players</h3>
            <ul className="player-list">
              {players.map(p => (
                <li key={p.id} className={`player-item${p.id === playerId ? ' self' : ''}`}>
                  {p.name}
                  {p.id === gameState.target_player.id  && ' 🎯'}
                  {p.id === gameState.final_decider.id  && ' 🔒'}
                </li>
              ))}
            </ul>
          </div>

          <ChatPanel chat={chat} playerId={playerId} />
        </aside>
      </div>
    </div>
  );
}
