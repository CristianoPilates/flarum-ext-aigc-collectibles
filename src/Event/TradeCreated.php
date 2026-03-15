<?php

namespace Donk\AigcCollectibles\Event;

use Donk\AigcCollectibles\Model\Trade;
use Flarum\User\User;

class TradeCreated
{
    public User $actor;
    public Trade $trade;

    public function __construct(User $actor, Trade $trade)
    {
        $this->actor = $actor;
        $this->trade = $trade;
    }
}
