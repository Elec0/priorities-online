<?php
declare(strict_types=1);

namespace Priorities\Models;

readonly class Player
{
    public function __construct(
        public int    $id,
        public int    $lobbyId,
        public string $name,
        public string $sessionToken,
        public bool   $isHost,
        public int    $turnOrder,
        public string $status,
        public string $joinedAt,
    ) {}
}
