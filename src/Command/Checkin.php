<?php

namespace Donk\AigcCollectibles\Command;

use Flarum\User\User;

class Checkin
{
    public User $actor;

    public function __construct(User $actor)
    {
        $this->actor = $actor;
    }
}
