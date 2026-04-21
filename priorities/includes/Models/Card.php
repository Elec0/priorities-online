<?php
declare(strict_types=1);

namespace Priorities\Models;

class Card
{
    public function __construct(
        public readonly int    $id,
        public readonly string $content,
        public readonly string $category,
        public readonly string $emoji,
        public readonly string $letter,
    ) {}
}
