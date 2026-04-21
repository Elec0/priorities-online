/**
 * Fisher-Yates shuffle: randomize array in place.
 */
export function shuffle<T>(arr: T[]): T[] {
  for (let i = arr.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [arr[i], arr[j]] = [arr[j], arr[i]];
  }
  return arr;
}

/**
 * Create a seeded shuffle for reproducible randomization.
 * Uses game_id and lobby_id to ensure same seed across all clients in a game.
 * Each player gets a unique display order because the seed includes playerId.
 */
export function createSeededShuffle(seed: number) {
  // Simple LCG (Linear Congruential Generator) for reproducible randomness
  let state = seed;
  
  return function seededRandom() {
    state = (state * 1664525 + 1013904223) % 4294967296;
    return (state >>> 0) / 4294967296;
  };
}

/**
 * Shuffle using a seeded random number generator.
 */
export function shuffleWithSeed<T>(arr: T[], seed: number): T[] {
  const random = createSeededShuffle(seed);
  const result = [...arr];
  for (let i = result.length - 1; i > 0; i--) {
    const j = Math.floor(random() * (i + 1));
    [result[i], result[j]] = [result[j], result[i]];
  }
  return result;
}
