<?php
declare(strict_types=1);

namespace Priorities\Models;

readonly class Round
{
    public function __construct(
        public int     $id,
        public int     $gameId,
        public int     $roundNumber,
        public int     $targetPlayerId,
        public int     $finalDeciderId,
        public array   $cardIds,
        public ?array  $targetRanking,
        public ?array  $groupRanking,
        public ?array  $result,
        public string  $status,
        public ?string $rankingDeadline,
    ) {}
}
