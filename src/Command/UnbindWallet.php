<?php

namespace Donk\AigcCollectibles\Command;

use Flarum\User\User;

class UnbindWallet
{
    public function __construct(
        public readonly User $actor,
        public readonly int $accountId,
    ) {}
}
