<?php

namespace Donk\AigcCollectibles\Service\Contracts;

use Flarum\User\User;

interface BlindBoxServiceInterface
{
    public function balanceOf(User $user): int;

    public function award(User $user, int $amount): void;

    public function spend(User $user, int $amount): void;

    public function transfer(User $from, User $to, int $amount): void;
}
