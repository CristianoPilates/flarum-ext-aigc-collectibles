<?php

namespace Donk\AigcCollectibles\Event;

use Donk\AigcCollectibles\Model\Collectible;
use Flarum\User\User;

class CollectibleGenerated
{
    public User $user;
    public Collectible $collectible;

    public function __construct(User $user, Collectible $collectible)
    {
        $this->user = $user;
        $this->collectible = $collectible;
    }
}
