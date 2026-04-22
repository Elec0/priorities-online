import { useState, FormEvent } from 'react';
import { createLobby, joinLobby } from '../api';

type Tab = 'create' | 'join';

function getDevProfile(): string {
  return new URLSearchParams(window.location.search).get('dev_profile') ?? '';
}

export function IndexPage() {
  const [tab, setTab]               = useState<Tab>('create');
  const [name, setName]             = useState('');
  const [code, setCode]             = useState('');
  const [timerEnabled, setTimerEnabled] = useState(true);
  const [timerSeconds, setTimerSeconds] = useState(60);
  const [error, setError]           = useState('');
  const [loading, setLoading]       = useState(false);

  const devProfile = getDevProfile();

  async function handleCreate(e: FormEvent) {
    e.preventDefault();
    setError('');
    setLoading(true);
    try {
      const res = await createLobby(name, timerEnabled, timerSeconds, devProfile || undefined);
      window.location.href = res.redirect_url;
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to create lobby');
      setLoading(false);
    }
  }

  async function handleJoin(e: FormEvent) {
    e.preventDefault();
    setError('');
    setLoading(true);
    try {
      const res = await joinLobby(name, code.toUpperCase(), devProfile || undefined);
      window.location.href = res.redirect_url;
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to join lobby');
      setLoading(false);
    }
  }

  return (
    <div className="index-page">
      <h1 className="logo">Priorities</h1>

      <div className="tabs">
        <button
          className={`tab-btn${tab === 'create' ? ' active' : ''}`}
          onClick={() => setTab('create')}
        >
          Create Lobby
        </button>
        <button
          className={`tab-btn${tab === 'join' ? ' active' : ''}`}
          onClick={() => setTab('join')}
        >
          Join Lobby
        </button>
      </div>

      {error && <p className="error-msg">{error}</p>}

      {tab === 'create' && (
        <form onSubmit={handleCreate} className="lobby-form">
          <label>
            Your name
            <input
              type="text"
              value={name}
              maxLength={50}
              required
              autoFocus
              onChange={e => setName(e.target.value)}
            />
          </label>
          <label className="checkbox-label">
            <input
              type="checkbox"
              checked={timerEnabled}
              onChange={e => setTimerEnabled(e.target.checked)}
            />
            Enable ranking timer
          </label>
          {timerEnabled && (
            <label>
              Timer duration (seconds)
              <input
                type="number"
                value={timerSeconds}
                min={10}
                max={600}
                required
                onChange={e => setTimerSeconds(Number(e.target.value))}
              />
            </label>
          )}
          <button type="submit" disabled={loading}>
            {loading ? 'Creating…' : 'Create Lobby'}
          </button>
        </form>
      )}

      {tab === 'join' && (
        <form onSubmit={handleJoin} className="lobby-form">
          <label>
            Lobby code
            <input
              type="text"
              value={code}
              maxLength={6}
              required
              autoFocus
              placeholder="ABCDEF"
              onChange={e => setCode(e.target.value.toUpperCase())}
            />
          </label>
          <label>
            Your name
            <input
              type="text"
              value={name}
              maxLength={50}
              required
              onChange={e => setName(e.target.value)}
            />
          </label>
          <button type="submit" disabled={loading}>
            {loading ? 'Joining…' : 'Join Lobby'}
          </button>
        </form>
      )}
    </div>
  );
}
