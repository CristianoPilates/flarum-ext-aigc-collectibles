<?php

namespace Donk\AigcCollectibles\Command;

use Flarum\User\User;

class CreateTrade
{
    public User $actor;
    /** @var array<string, mixed> */
    public array $data;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(User $actor, array $data)
    {
        $this->actor = $actor;
        $this->data = $data;
    }
}
