<?php

namespace Donk\AigcCollectibles\Service;

use Donk\AigcCollectibles\Model\BarterProposal;
use Donk\AigcCollectibles\Model\Collectible;
use Donk\AigcCollectibles\Model\CollectibleEvent;
use Donk\AigcCollectibles\Service\Contracts\BlindBoxServiceInterface;
use Flarum\Foundation\ValidationException;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;

class BarterSettlementService
{
    public function __construct(
        private readonly BlindBoxServiceInterface $blindBoxService,
    ) {}

    public function settle(BarterProposal $proposal, ConnectionInterface $db): BarterProposal
    {
        /** @var BarterProposal $proposal */
        $proposal = BarterProposal::query()
            ->where('id', $proposal->id)
            ->lockForUpdate()
            ->firstOrFail()
            ->load('items');

        if ($proposal->status !== BarterProposal::STATUS_SETTLING) {
            throw new ValidationException(['proposal' => 'This barter proposal is no longer settling.']);
        }

        $proposer = User::query()->where('id', $proposal->proposer_user_id)->lockForUpdate()->firstOrFail();
        $counterparty = User::query()->where('id', $proposal->counterparty_user_id)->lockForUpdate()->firstOrFail();

        $transferBlindBoxIds = [
            $proposer->id => [],
            $counterparty->id => [],
        ];

        foreach ($proposal->items as $item) {
            $targetUserId = $item->owner_user_id === $proposer->id ? $counterparty->id : $proposer->id;

            if ($item->asset_type === BarterProposal::ASSET_COLLECTIBLE) {
                $this->transferCollectible(
                    collectibleId: $item->asset_id,
                    fromUserId: $item->owner_user_id,
                    toUserId: $targetUserId,
                    proposal: $proposal,
                );

                continue;
            }

            if ($item->asset_type === BarterProposal::ASSET_BLIND_BOX) {
                $transferBlindBoxIds[$item->owner_user_id][] = $item->asset_id;

                continue;
            }

            throw new ValidationException(['items' => 'Unsupported asset type: ' . $item->asset_type]);
        }

        if ($transferBlindBoxIds[$proposer->id] !== []) {
            $this->blindBoxService->transferSpecific($proposer, $counterparty, $transferBlindBoxIds[$proposer->id]);
        }

        if ($transferBlindBoxIds[$counterparty->id] !== []) {
            $this->blindBoxService->transferSpecific($counterparty, $proposer, $transferBlindBoxIds[$counterparty->id]);
        }

        return $proposal->load(['items.ownerUser', 'proposer', 'counterparty', 'acceptedBy']);
    }

    private function transferCollectible(int $collectibleId, int $fromUserId, int $toUserId, BarterProposal $proposal): void
    {
        /** @var Collectible $collectible */
        $collectible = Collectible::query()
            ->where('id', $collectibleId)
            ->lockForUpdate()
            ->firstOrFail();

        if ($collectible->owner_id !== $fromUserId) {
            throw new ValidationException(['items' => 'A collectible in this barter proposal no longer belongs to its recorded owner.']);
        }

        $fromUser = User::query()->where('id', $fromUserId)->lockForUpdate()->firstOrFail();

        if ((int) $fromUser->showcase_collectible_id === (int) $collectible->id) {
            $fromUser->showcase_collectible_id = null;
            $fromUser->save();
        }

        $collectible->owner_id = $toUserId;
        $collectible->times_traded += 1;
        $collectible->save();

        $event = CollectibleEvent::log(
            collectible: $collectible,
            eventType: 'traded',
            fromUserId: $fromUserId,
            toUserId: $toUserId,
            tradeId: null,
            metadata: [
                'barterProposalId' => $proposal->id,
                'threadType' => $proposal->thread_type,
                'threadId' => $proposal->thread_id,
            ],
        );
        $event->save();
    }
}
