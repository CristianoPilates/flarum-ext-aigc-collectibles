<?php

namespace Donk\AigcCollectibles\Command;

use Donk\AigcCollectibles\Model\BlindBox;
use Donk\AigcCollectibles\Service\Contracts\BlindBoxServiceInterface;

class AppraiseBlindBoxHandler
{
    public function __construct(
        private readonly BlindBoxServiceInterface $blindBox,
    ) {}

    public function handle(AppraiseBlindBox $command): BlindBox
    {
        return $this->blindBox->appraise(
            $command->actor,
            $command->boxId,
            $command->nonce,
            $command->hash,
        );
    }
}
