<?php

namespace Donk\AigcCollectibles\Event;

use Donk\AigcCollectibles\Model\CheckinRecord;
use Flarum\User\User;

class CheckedIn
{
    public function __construct(
        public User $user,
        public CheckinRecord $record,
        public int $rewardAmount,
    ) {}
}
