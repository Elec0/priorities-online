import type {
  CardState,
  ChatMessage,
  GameState,
  LetterMapState,
  PlayerState,
  RoundState,
  ScoreResultState,
} from '../../priorities/assets/js/types';

export const emptyLetters: LetterMapState = { P: 0, R: 0, I: 0, O: 0, T: 0, E: 0, S: 0 };

export const cards: CardState[] = [
  { id: 1, content: 'Pizza',     category: 'food',   emoji: '🍕', letter: 'P' },
  { id: 2, content: 'Rain',      category: 'weather', emoji: '🌧', letter: 'R' },
  { id: 3, content: 'Ice cream', category: 'food',   emoji: '🍦', letter: 'I' },
];

export const playerAlice: PlayerState = { id: 10, name: 'Alice', turn_order: 0, is_host: true,  status: 'active' };
export const playerBob:   PlayerState = { id: 20, name: 'Bob',   turn_order: 1, is_host: false, status: 'active' };
export const playerCarol: PlayerState = { id: 30, name: 'Carol', turn_order: 2, is_host: false, status: 'active' };

export const chatMessages: ChatMessage[] = [
  { id: 1, player_id: 10, player_name: 'Alice', message: 'Hello!',      created_at: '2026-04-20T12:00:00Z' },
  { id: 2, player_id: 20, player_name: 'Bob',   message: 'Hey there',   created_at: '2026-04-20T12:00:01Z' },
  { id: 3, player_id: null, player_name: null,  message: 'Game started', created_at: '2026-04-20T12:00:02Z' },
];

export const scores: ScoreResultState[] = [
  { card_id: 1, correct: true },
  { card_id: 2, correct: false },
  { card_id: 3, correct: true },
];

function makeRound(overrides: Partial<RoundState> = {}): RoundState {
  return {
    id: 1,
    number: 1,
    status: 'ranking',
    card_ids: [1, 2, 3],
    cards,
    group_ranking: null,
    ranking_deadline: null,
    ...overrides,
  };
}

export function makeGameState(overrides: Partial<GameState> = {}): GameState {
  return {
    state_version: 1,
    lobby_status: 'playing',
    lobby_code: 'ABCDEF',
    game_id: 99,
    game_status: 'active',
    round: makeRound(),
    target_player: playerAlice,
    final_decider: playerBob,
    players: [playerAlice, playerBob, playerCarol],
    player_letters: emptyLetters,
    game_letters: emptyLetters,
    chat: [],
    ...overrides,
  };
}
