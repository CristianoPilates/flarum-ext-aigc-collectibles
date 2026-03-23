<?php

namespace Donk\AigcCollectibles\Command;

use Donk\AigcCollectibles\Model\CheckinRecord;
use Donk\AigcCollectibles\Service\Contracts\CheckinServiceInterface;

class CheckinHandler
{
    public function __construct(
        private CheckinServiceInterface $checkinService
    ) {}

    public function handle(Checkin $command): CheckinRecord
    {
        // 从DTO中取出actor
        $actor = $command->actor;

        // 权限检查（Guest 会在这里抛异常 → 401）
        $actor->assertRegistered();

        return $this->checkinService->performCheckin($actor);
    }
}
