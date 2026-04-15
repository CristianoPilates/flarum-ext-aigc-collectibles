<?php

namespace Donk\AigcCollectibles\Service\Contracts;

use Donk\AigcCollectibles\Model\BarterProposal;
use Flarum\User\User;

interface BarterServiceInterface
{
    /**
     * @return array<string, mixed>
     */
    public function listThreadAssets(
        User $actor,
        string $threadType,
        int $threadId,
        int $counterpartyUserId
    ): array;

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function createProposal(
        User $actor,
        string $threadType,
        int $threadId,
        int $counterpartyUserId,
        array $items,
        ?string $message = null,
        ?int $replacesProposalId = null
    ): BarterProposal;

    public function acceptProposal(BarterProposal $proposal, User $actor): BarterProposal;

    public function rejectProposal(BarterProposal $proposal, User $actor): BarterProposal;

    public function cancelProposal(BarterProposal $proposal, User $actor): BarterProposal;
}
