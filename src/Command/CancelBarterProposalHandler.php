<?php

namespace Donk\AigcCollectibles\Command;

use Donk\AigcCollectibles\Model\BarterProposal;
use Donk\AigcCollectibles\Service\Contracts\BarterServiceInterface;

class CancelBarterProposalHandler
{
    public function __construct(
        private readonly BarterServiceInterface $barterService,
    ) {}

    public function handle(CancelBarterProposal $command): BarterProposal
    {
        $command->actor->assertRegistered();

        $proposal = BarterProposal::query()->findOrFail($command->proposalId);

        return $this->barterService->cancelProposal($proposal, $command->actor);
    }
}
