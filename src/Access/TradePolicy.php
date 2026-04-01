<?php

namespace Donk\AigcCollectibles\Access;

use Donk\AigcCollectibles\Model\Trade;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

class TradePolicy extends AbstractPolicy
{
    public function view(User $actor, Trade $trade): ?string
    {
        if ($actor->id === $trade->from_user_id || $actor->id === $trade->to_user_id) {
            return $this->allow();
        }

        if (in_array($trade->status, [Trade::STATUS_ACCEPTED, Trade::STATUS_COMPLETED], true)) {
            return $this->allow();
        }
    }

    public function accept(User $actor, Trade $trade): ?string
    {
        if ($actor->id === $trade->to_user_id && $trade->status === Trade::STATUS_PENDING) {
            return $this->allow();
        }

        return $this->deny();
    }

    public function reject(User $actor, Trade $trade): ?string
    {
        if ($actor->id === $trade->to_user_id && $trade->status === Trade::STATUS_PENDING) {
            return $this->allow();
        }

        return $this->deny();
    }

    public function cancel(User $actor, Trade $trade): ?string
    {
        if ($actor->id === $trade->from_user_id && $trade->status === Trade::STATUS_PENDING) {
            return $this->allow();
        }

        return $this->deny();
    }
}
