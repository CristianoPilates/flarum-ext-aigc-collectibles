<?php

namespace Donk\AigcCollectibles\Command;

use Donk\AigcCollectibles\Model\BarterProposal;
use Donk\AigcCollectibles\Service\Contracts\BarterServiceInterface;

class RejectBarterProposalHandler
{
    public function __construct(
        private readonly BarterServiceInterface $barterService,
    ) {}

    public function handle(RejectBarterProposal $command): BarterProposal
    {
        $command->actor->assertRegistered();

        $proposal = BarterProposal::query()->findOrFail($command->proposalId);

        return $this->barterService->rejectProposal($proposal, $command->actor);
    }
}
