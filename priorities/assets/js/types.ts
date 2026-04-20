// Shared TypeScript interfaces mirroring stream.php JSON payloads

export interface LetterMapState {
  P: number;
  R: number;
  I: number;
  O: number;
  T: number;
  E: number;
  S: number;
}

export interface CardState {
  id: number;
  content: string;
  category: string;
  emoji: string;
  letter: string;
}

export interface ScoreResultState {
  card_id: number;
  correct: boolean;
}

export interface PlayerState {
  id: number;
  name: string;
  turn_order: number;
  is_host: boolean;
  status: 'active' | 'kicked';
}

export interface RoundState {
  id: number;
  number: number;
  status: 'ranking' | 'guessing' | 'revealed' | 'skipped';
  card_ids: number[];
  cards: CardState[];
  /** Group's current guess ordering (card IDs). Null until set. */
  group_ranking: number[] | null;
  /** Only present when status === 'revealed' */
  target_ranking?: number[];
  /** Only present when status === 'revealed' */
  result?: ScoreResultState[];
  /** ISO datetime string; null after target submits */
  ranking_deadline: string | null;
}

export interface ChatMessage {
  id: number;
  player_id: number | null;
  player_name: string | null;
  message: string;
  created_at: string;
}

// Payload when lobby_status === 'waiting'
export interface LobbyState {
  state_version: number;
  lobby_status: 'waiting';
  lobby_code: string;
  game_id: null;
  players: PlayerState[];
  chat: ChatMessage[];
}

// Payload when lobby_status === 'playing'
export interface GameState {
  state_version: number;
  lobby_status: 'playing';
  lobby_code: string;
  game_id: number;
  game_status: 'active' | 'players_win' | 'game_wins' | 'draw';
  round: RoundState;
  target_player: PlayerState;
  final_decider: PlayerState;
  players: PlayerState[];
  player_letters: LetterMapState;
  game_letters: LetterMapState;
  chat: ChatMessage[];
}

export type StreamState = LobbyState | GameState;
