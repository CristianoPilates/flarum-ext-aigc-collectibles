<?php

namespace Donk\AigcCollectibles\Event;

use Donk\AigcCollectibles\Model\Collectible;
use Flarum\User\User;

class BlindBoxOpened
{
    public User $user;
    public Collectible $collectible;
    public string $rarity;

    public function __construct(User $user, Collectible $collectible, string $rarity)
    {
        $this->user = $user;
        $this->collectible = $collectible;
        $this->rarity = $rarity;
    }
}
