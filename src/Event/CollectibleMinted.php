<?php

namespace Donk\AigcCollectibles\Event;

use Donk\AigcCollectibles\Model\Collectible;
use Flarum\User\User;

class CollectibleMinted
{
    public User $user;
    public Collectible $collectible;
    public int $tokenId;

    public function __construct(User $user, Collectible $collectible, int $tokenId)
    {
        $this->user = $user;
        $this->collectible = $collectible;
        $this->tokenId = $tokenId;
    }
}
