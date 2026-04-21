<?php
declare(strict_types=1);

namespace Priorities\Models;

class Game
{
    public function __construct(
        public readonly int       $id,
        public readonly int       $lobbyId,
        public readonly int       $currentRound,
        public readonly int       $targetPlayerIndex,
        public readonly int       $finalDeciderIndex,
        public readonly string    $status,
        public readonly LetterMap $playerLetters,
        public readonly LetterMap $gameLetters,
        public readonly array     $deckOrder,
        public readonly int       $stateVersion,
        public readonly string    $createdAt,
    ) {}
}
