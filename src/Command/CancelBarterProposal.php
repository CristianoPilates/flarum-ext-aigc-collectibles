<?php

namespace Donk\AigcCollectibles\Command;

use Flarum\User\User;

class CancelBarterProposal
{
    public function __construct(
        public readonly int $proposalId,
        public readonly User $actor,
    ) {}
}
