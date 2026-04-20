<?php
declare(strict_types=1);

namespace Priorities\Models;

readonly class ScoreResult
{
    public function __construct(
        public int  $cardId,
        public bool $correct,
    ) {}
}
