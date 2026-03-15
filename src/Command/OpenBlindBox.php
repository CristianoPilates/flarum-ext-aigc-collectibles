<?php

namespace Donk\AigcCollectibles\Command;

use Flarum\User\User;

class OpenBlindBox
{
    public User $actor;
    public array $data;

    public function __construct(User $actor, array $data = [])
    {
        $this->actor = $actor;
        $this->data = $data;
    }
}
