<?php

namespace Donk\AigcCollectibles\Command;

use Donk\AigcCollectibles\Model\CheckinRecord;
use Donk\AigcCollectibles\Service\Contracts\CheckinServiceInterface;

class CheckinHandler
{
    protected CheckinServiceInterface $checkinService;

    public function __construct(CheckinServiceInterface $checkinService)
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
