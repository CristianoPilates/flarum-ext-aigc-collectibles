<?php

namespace Donk\AigcCollectibles\Event;

use Donk\AigcCollectibles\Model\Web3Account;
use Flarum\User\User;

class WalletUnbound
{
    public function __construct(
        public readonly User $user,
        public readonly Web3Account $account,
    ) {}
}
