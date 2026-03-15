<?php

namespace Donk\AigcCollectibles\Command;

use Flarum\User\User;

class MintCollectible
{
    public int $collectibleId;
    public User $actor;

    public function __construct(int $collectibleId, User $actor)
    {
        $this->collectibleId = $collectibleId;
        $this->actor = $actor;
    }
}
