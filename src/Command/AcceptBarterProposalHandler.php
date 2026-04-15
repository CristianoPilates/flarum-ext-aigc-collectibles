<?php

namespace Donk\AigcCollectibles\Command;

use Donk\AigcCollectibles\Model\BarterProposal;
use Donk\AigcCollectibles\Service\Contracts\BarterServiceInterface;

class AcceptBarterProposalHandler
{
    public function __construct(
        private readonly BarterServiceInterface $barterService,
    ) {}

    public function handle(AcceptBarterProposal $command): BarterProposal
    {
        $command->actor->assertRegistered();

        $proposal = BarterProposal::query()->findOrFail($command->proposalId);

        return $this->barterService->acceptProposal($proposal, $command->actor);
    }
}
