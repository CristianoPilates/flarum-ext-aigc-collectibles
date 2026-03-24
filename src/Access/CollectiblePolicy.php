<?php

namespace Donk\AigcCollectibles\Access;

use Donk\AigcCollectibles\Model\Collectible;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

class CollectiblePolicy extends AbstractPolicy
{
    public function view(User $actor, Collectible $collectible)
    {
        if ($collectible->status === Collectible::STATUS_COMPLETED) {
            return $this->allow();
        }

        if ($actor->id === $collectible->owner_id) {
            return $this->allow();
        }
    }

    public function update(User $actor, Collectible $collectible)
    {
        if ($actor->id === $collectible->owner_id) {
            return $this->allow();
        }
    }

    public function trade(User $actor, Collectible $collectible)
    {
        if ($actor->id === $collectible->owner_id && $collectible->status === Collectible::STATUS_COMPLETED) {
            return $this->allow();
        }

        return $this->deny();
    }

    public function mint(User $actor, Collectible $collectible)
    {
        if ($actor->id === $collectible->owner_id
            && $collectible->status === Collectible::STATUS_COMPLETED
            && $collectible->token_id === null) {
            return $this->allow();
        }

        return $this->deny();
    }
}
