const BASE = '/priorities/api';

function devProfileParam(): string {
  const p = new URLSearchParams(window.location.search).get('dev_profile');
  return p ? `?dev_profile=${encodeURIComponent(p)}` : '';
}

async function post<T>(endpoint: string, body: Record<string, unknown> | FormData): Promise<T> {
  const isFormData = body instanceof FormData;
  const res = await fetch(`${BASE}/${endpoint}${devProfileParam()}`, {
    method: 'POST',
    headers: isFormData ? undefined : { 'Content-Type': 'application/json' },
    body: isFormData ? body : JSON.stringify(body),
  });
  const json = await res.json();
  if (!res.ok) {
    throw new Error((json as { error: string }).error ?? `HTTP ${res.status}`);
  }
  return json as T;
}

// ── Lobby management ─────────────────────────────────────────────────────────

export interface CreateLobbyResponse {
  success: true;
  code: string;
  lobby_id: number;
  player_id: number;
  redirect_url: string;
}

export function createLobby(name: string, timerEnabled: boolean, timerSeconds: number, devProfile?: string): Promise<CreateLobbyResponse> {
  const fd = new FormData();
  fd.append('name', name);
  fd.append('timer_enabled', timerEnabled ? '1' : '0');
  fd.append('timer_seconds', String(timerSeconds));
  if (devProfile) fd.append('dev_profile', devProfile);
  return post<CreateLobbyResponse>('create_lobby.php', fd);
}

export interface JoinLobbyResponse {
  success: true;
  lobby_id: number;
  player_id: number;
  redirect_url: string;
}

export function joinLobby(name: string, code: string, devProfile?: string): Promise<JoinLobbyResponse> {
  const fd = new FormData();
  fd.append('name', name);
  fd.append('code', code);
  if (devProfile) fd.append('dev_profile', devProfile);
  return post<JoinLobbyResponse>('join_lobby.php', fd);
}

export interface StartGameResponse {
  success: true;
  game_id: number;
}

export function startGame(lobbyId: number): Promise<StartGameResponse> {
  return post<StartGameResponse>('start_game.php', { lobby_id: lobbyId });
}

// ── Gameplay ──────────────────────────────────────────────────────────────────

export function submitRanking(ranking: number[]): Promise<{ success: true }> {
  return post('submit_ranking.php', { ranking });
}

export function updateGuess(ranking: number[]): Promise<{ success: true }> {
  return post('update_guess.php', { ranking });
}

export function lockInGuess(): Promise<{ success: true }> {
  return post('lock_in_guess.php', {});
}

export function nextRound(): Promise<{ success: true }> {
  return post('next_round.php', {});
}

// ── Social ────────────────────────────────────────────────────────────────────

export function sendMessage(message: string): Promise<{ success: true }> {
  return post('send_message.php', { message });
}

export function kickPlayer(playerId: number): Promise<{ success: true }> {
  return post('kick_player.php', { player_id: playerId });
}
