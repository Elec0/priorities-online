<?php
declare(strict_types=1);

namespace Priorities\Models;

class Player
{
    public function __construct(
        public readonly int    $id,
        public readonly int    $lobbyId,
        public readonly string $name,
        public readonly string $sessionToken,
        public readonly bool   $isHost,
        public readonly int    $turnOrder,
        public readonly string $status,
        public readonly string $joinedAt,
    ) {}
}
