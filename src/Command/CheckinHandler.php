<?php

namespace Donk\AigcCollectibles\Command;

use Donk\AigcCollectibles\Model\CheckinRecord;
use Donk\AigcCollectibles\Service\CheckinService;

class CheckinHandler
{
    protected CheckinService $checkinService;

    public function __construct(CheckinService $checkinService)
    {
        $this->checkinService = $checkinService;
    }

    public function handle(Checkin $command): CheckinRecord
    {
        $actor = $command->actor;

        $actor->assertRegistered();

        return $this->checkinService->performCheckin($actor);
    }
}
