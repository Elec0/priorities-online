<?php
declare(strict_types=1);

namespace Priorities\Models;

readonly class Lobby
{
    public function __construct(
        public int    $id,
        public string $code,
        public string $hostToken,
        public string $status,
        public string $createdAt,
        public string $updatedAt,
    ) {}
}
