<?php

namespace Donk\AigcCollectibles\Event;

use Donk\AigcCollectibles\Model\Trade;
use Flarum\User\User;

class TradeCompleted
{
    public User $actor;
    public Trade $trade;
    public string $outcome;

    public function __construct(User $actor, Trade $trade, string $outcome)
    {
        $this->actor = $actor;
        $this->trade = $trade;
        $this->outcome = $outcome;
    }
}
