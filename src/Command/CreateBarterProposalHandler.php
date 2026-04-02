<?php

namespace Donk\AigcCollectibles\Command;

use Donk\AigcCollectibles\Model\BarterProposal;
use Donk\AigcCollectibles\Service\Contracts\BarterServiceInterface;
use Illuminate\Support\Arr;

class CreateBarterProposalHandler
{
    public function __construct(
        private readonly BarterServiceInterface $barterService,
    ) {}

    public function handle(CreateBarterProposal $command): BarterProposal
    {
        $actor = $command->actor;
        $data = $command->data;

        $actor->assertRegistered();

        /** @var array<int, array<string, mixed>> $items */
        $items = Arr::get($data, 'data.attributes.items', []);

        return $this->barterService->createProposal(
            actor: $actor,
            threadType: (string) Arr::get($data, 'data.attributes.threadType', BarterProposal::THREAD_DIALOG),
            threadId: (int) Arr::get($data, 'data.attributes.threadId'),
            counterpartyUserId: (int) Arr::get($data, 'data.attributes.counterpartyUserId'),
            items: $items,
            message: Arr::get($data, 'data.attributes.message'),
            replacesProposalId: Arr::get($data, 'data.attributes.replacesProposalId') !== null
                ? (int) Arr::get($data, 'data.attributes.replacesProposalId')
                : null,
        );
    }
}
