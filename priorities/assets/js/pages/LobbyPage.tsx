import { useEffect } from 'react';
import { useSSE } from '../hooks/useSSE';
import { startGame, kickPlayer } from '../api';
import { ChatPanel } from '../components/ChatPanel';
import type { LobbyState } from '../types';

function getRootData() {
  const el = document.getElementById('root')!;
  return {
    lobbyId:    parseInt(el.dataset.lobbyId ?? '0', 10),
    playerId:   parseInt(el.dataset.playerId ?? '0', 10),
    isHost:     el.dataset.isHost === '1',
    devProfile: el.dataset.devProfile ?? '',
  };
}

export function LobbyPage() {
  const { lobbyId, playerId, isHost, devProfile } = getRootData();
  const { state } = useSSE(lobbyId, 0);

  // Redirect to game page once lobby transitions to 'playing'.
  useEffect(() => {
    if (state && state.lobby_status === 'playing') {
      const query = devProfile ? `?lobby_id=${lobbyId}&dev_profile=${devProfile}` : `?lobby_id=${lobbyId}`;
      window.location.href = `game.php${query}`;
    }
  }, [state, lobbyId, devProfile]);

  async function handleStart() {
    try {
      await startGame(lobbyId);
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Failed to start game');
    }
  }

  async function handleKick(targetId: number) {
    try {
      await kickPlayer(targetId);
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Failed to kick player');
    }
  }

  const lobbyState = state as LobbyState | null;

  return (
    <div className="lobby-page">
      <h1>Lobby</h1>

      {lobbyState && (
        <p className="lobby-code">
          Code: <strong>{lobbyState.lobby_code}</strong>
        </p>
      )}

      <div className="lobby-layout">
        <div className="lobby-main">
          <h2>Players ({lobbyState?.players.length ?? 0})</h2>
          <ul className="player-list">
            {lobbyState?.players.map(p => (
              <li key={p.id} className="player-item">
                <span>
                  {p.name}
                  {p.is_host && <span className="role-icon" title="Host">👑</span>}
                </span>
                {isHost && p.id !== playerId && (
                  <button
                    className="kick-btn"
                    onClick={() => handleKick(p.id)}
                  >
                    Kick
                  </button>
                )}
              </li>
            ))}
          </ul>

          {isHost && (
            <button
              className="start-btn"
              disabled={!lobbyState || lobbyState.players.length < 3}
              onClick={handleStart}
            >
              {!lobbyState || lobbyState.players.length < 3
                ? `Need ${3 - (lobbyState?.players.length ?? 0)} more player(s)`
                : 'Start Game'}
            </button>
          )}

          {!isHost && (
            <p className="waiting-msg">Waiting for the host to start the game…</p>
          )}
        </div>

        <ChatPanel
          chat={lobbyState?.chat ?? []}
          playerId={playerId}
        />
      </div>
    </div>
  );
}
