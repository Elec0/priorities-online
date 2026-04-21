<?php
declare(strict_types=1);

namespace Priorities\Models;

readonly class LetterMap
{
    public function __construct(
        public int $P,
        public int $R,
        public int $I,
        public int $O,
        public int $T,
        public int $E,
        public int $S,
    ) {}

    /** Win condition: P≥1, R≥2, I≥3, O≥1, T≥1, E≥1, S≥1 */
    public function checkWin(): bool
    {
        return $this->P >= 1
            && $this->R >= 2
            && $this->I >= 3
            && $this->O >= 1
            && $this->T >= 1
            && $this->E >= 1
            && $this->S >= 1;
    }

    /** Return a new LetterMap with the given letter's count incremented by 1. */
    public function withIncrement(string $letter): self
    {
        return new self(
            P: $this->P + ($letter === 'P' ? 1 : 0),
            R: $this->R + ($letter === 'R' ? 1 : 0),
            I: $this->I + ($letter === 'I' ? 1 : 0),
            O: $this->O + ($letter === 'O' ? 1 : 0),
            T: $this->T + ($letter === 'T' ? 1 : 0),
            E: $this->E + ($letter === 'E' ? 1 : 0),
            S: $this->S + ($letter === 'S' ? 1 : 0),
        );
    }

    /** @return array<string,int> */
    public function toArray(): array
    {
        return [
            'P' => $this->P,
            'R' => $this->R,
            'I' => $this->I,
            'O' => $this->O,
            'T' => $this->T,
            'E' => $this->E,
            'S' => $this->S,
        ];
    }
}
