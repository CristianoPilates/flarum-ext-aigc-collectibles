<?php

namespace Donk\AigcCollectibles\Service\Contracts;

use Donk\AigcCollectibles\Model\CheckinRecord;
use Flarum\User\User;

interface CheckinServiceInterface
{
    public function performCheckin(User $user): CheckinRecord;

    public function hasCheckedInToday(User $user): bool;
}
