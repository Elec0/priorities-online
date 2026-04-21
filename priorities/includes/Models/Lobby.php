<?php
declare(strict_types=1);

namespace Priorities\Models;

class Lobby
{
    public function __construct(
        public readonly int    $id,
        public readonly string $code,
        public readonly string $hostToken,
        public readonly string $status,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}
}
