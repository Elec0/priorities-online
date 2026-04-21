<?php
declare(strict_types=1);

namespace Priorities\Models;

class Round
{
    public function __construct(
        public readonly int     $id,
        public readonly int     $gameId,
        public readonly int     $roundNumber,
        public readonly int     $targetPlayerId,
        public readonly int     $finalDeciderId,
        public readonly array   $cardIds,
        public readonly ?array  $targetRanking,
        public readonly ?array  $groupRanking,
        public readonly ?array  $result,
        public readonly string  $status,
        public readonly ?string $rankingDeadline,
    ) {}
}
