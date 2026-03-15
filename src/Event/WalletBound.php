<?php

namespace Donk\AigcCollectibles\Event;

use Donk\AigcCollectibles\Model\Web3Account;
use Flarum\User\User;

class WalletBound
{
    public User $user;
    public Web3Account $account;

    public function __construct(User $user, Web3Account $account)
    {
        $this->user = $user;
        $this->account = $account;
    }
}
