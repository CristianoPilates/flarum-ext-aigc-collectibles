<?php

namespace Donk\AigcCollectibles\Command;

use Flarum\User\User;

class AppraiseBlindBox
{
    public function __construct(
        public readonly User $actor,
        public readonly int $boxId,
        public readonly string $nonce,
        public readonly string $hash,
    ) {}
}
