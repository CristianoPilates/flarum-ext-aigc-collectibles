<?php

namespace Donk\AigcCollectibles\Command;

use Flarum\User\User;

class RejectTrade
{
    public int $tradeId;
    public User $actor;

    public function __construct(int $tradeId, User $actor)
    {
        $this->tradeId = $tradeId;
        $this->actor = $actor;
    }
}
