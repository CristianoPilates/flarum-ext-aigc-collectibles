<?php

namespace Donk\AigcCollectibles\Access;

use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

class CheckinPolicy extends AbstractPolicy
{
    protected $model = User::class;

    public function checkin(User $actor, User $user): ?string
    {
        if ($actor->id === $user->id && !$actor->isGuest()) {
            return $this->allow();
        }

        return $this->deny();
    }
}
