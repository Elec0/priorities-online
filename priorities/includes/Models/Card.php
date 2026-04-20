<?php
declare(strict_types=1);

namespace Priorities\Models;

readonly class Card
{
    public function __construct(
        public int    $id,
        public string $content,
        public string $category,
        public string $emoji,
        public string $letter,
    ) {}
}
