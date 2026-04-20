import type { LetterMapState } from '../types';

// The PRIORITIES sequence: 10 tiles, with repeated letters representing thresholds.
// e.g. R appears at positions 1 and 4 (R≥1 and R≥2), I at 2, 5, 7 (I≥1, I≥2, I≥3).
const SEQUENCE: Array<keyof LetterMapState> = ['P','R','I','O','R','I','T','I','E','S'];

interface Props {
  label: string;
  letters: LetterMapState;
}

export function LetterTiles({ label, letters }: Props) {
  // Track how many of each letter we've "used" as we walk the sequence.
  const consumed: Partial<Record<keyof LetterMapState, number>> = {};

  return (
    <div className="letter-tiles">
      <span className="tiles-label">{label}</span>
      <div className="tiles-row">
        {SEQUENCE.map((letter, idx) => {
          const threshold = (consumed[letter] ?? 0) + 1;
          consumed[letter] = threshold;
          const filled = letters[letter] >= threshold;
          return (
            <div
              key={idx}
              className={`tile${filled ? ' filled' : ' hollow'}`}
              aria-label={`${letter} ${filled ? 'collected' : 'empty'}`}
            >
              {letter}
            </div>
          );
        })}
      </div>
    </div>
  );
}
