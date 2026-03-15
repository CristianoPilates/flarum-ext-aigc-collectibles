<?php

namespace Donk\AigcCollectibles\Event;

use Donk\AigcCollectibles\Model\CheckinRecord;
use Flarum\User\User;

class CheckedIn
{
    public User $user;
    public CheckinRecord $record;
    public int $rewardAmount;

    public function __construct(User $user, CheckinRecord $record, int $rewardAmount)
    {
        $this->user = $user;
        $this->record = $record;
        $this->rewardAmount = $rewardAmount;
    }
}
