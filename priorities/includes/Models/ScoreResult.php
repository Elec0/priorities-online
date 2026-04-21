<?php
declare(strict_types=1);

namespace Priorities\Models;

class ScoreResult
{
    public function __construct(
        public readonly int  $cardId,
        public readonly bool $correct,
    ) {}
}
