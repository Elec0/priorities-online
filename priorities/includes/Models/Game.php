<?php
declare(strict_types=1);

namespace Priorities\Models;

readonly class Game
{
    public function __construct(
        public int       $id,
        public int       $lobbyId,
        public int       $currentRound,
        public int       $targetPlayerIndex,
        public int       $finalDeciderIndex,
        public string    $status,
        public LetterMap $playerLetters,
        public LetterMap $gameLetters,
        public array     $deckOrder,
        public int       $stateVersion,
        public string    $createdAt,
    ) {}
}
