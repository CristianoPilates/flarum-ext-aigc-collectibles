<?php

namespace Donk\AigcCollectibles\Service;

use Donk\AigcCollectibles\Model\BarterProposal;
use Donk\AigcCollectibles\Model\BarterProposalItem;
use Donk\AigcCollectibles\Model\BlindBox;
use Donk\AigcCollectibles\Model\Collectible;
use Donk\AigcCollectibles\Model\CollectibleEvent;
use Donk\AigcCollectibles\Service\Contracts\BarterServiceInterface;
use Donk\AigcCollectibles\Service\Contracts\BlindBoxServiceInterface;
use Flarum\Foundation\ValidationException;
use Flarum\User\User;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Arr;
use SM\Factory\FactoryInterface;

class BarterService implements BarterServiceInterface
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly BlindBoxServiceInterface $blindBoxService,
        private readonly FactoryInterface $stateMachines,
    ) {}

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
    ): BarterProposal {
        if ($threadId < 1) {
            throw new ValidationException(['thread_id' => 'Thread ID is required.']);
        }

        if ($counterpartyUserId < 1 || $counterpartyUserId === $actor->id) {
            throw new ValidationException(['counterparty_user_id' => 'A valid counterparty is required.']);
        }

        if ($items === []) {
            throw new ValidationException(['items' => 'At least one asset must be included.']);
        }

        $threadType = trim($threadType) ?: BarterProposal::THREAD_DIALOG;

        return $this->db->transaction(function () use (
            $actor,
            $threadType,
            $threadId,
            $counterpartyUserId,
            $items,
            $message,
            $replacesProposalId
        ) {
            $counterparty = User::query()->where('id', $counterpartyUserId)->firstOrFail();
            $participants = $this->resolveThreadParticipants($threadType, $threadId);

            if ($participants !== []) {
                if (! in_array($actor->id, $participants, true) || ! in_array($counterparty->id, $participants, true)) {
                    throw new ValidationException(['thread' => 'Both users must belong to the target private message thread.']);
                }
            }

            $normalizedItems = $this->normalizeItems($items, $actor->id, $counterparty->id);

            $revisionNumber = $this->nextRevisionNumber($threadType, $threadId);

            $proposal = new BarterProposal();
            $proposal->thread_type = $threadType;
            $proposal->thread_id = $threadId;
            $proposal->proposer_user_id = $actor->id;
            $proposal->counterparty_user_id = $counterparty->id;
            $proposal->status = BarterProposal::STATUS_PROPOSED;
            $proposal->message = $message;
            $proposal->revision_number = $revisionNumber;

            if ($replacesProposalId !== null) {
                /** @var BarterProposal $replaced */
                $replaced = BarterProposal::query()->lockForUpdate()->findOrFail($replacesProposalId);

                if ($replaced->thread_type !== $threadType || $replaced->thread_id !== $threadId) {
                    throw new ValidationException(['replaces_proposal_id' => 'Replacement proposal must belong to the same thread.']);
                }

                if (
                    ! $this->proposalInvolvesUser($replaced, $actor->id)
                    || ! $this->proposalInvolvesUser($replaced, $counterparty->id)
                ) {
                    throw new ValidationException(['replaces_proposal_id' => 'Replacement proposal participants do not match this thread.']);
                }

                if ($replaced->status !== BarterProposal::STATUS_PROPOSED) {
                    throw new ValidationException(['replaces_proposal_id' => 'Only a proposed barter can be replaced.']);
                }

                $this->stateMachines->get($replaced, 'barterProposal')->apply('supersede');
                $replaced->save();

                $proposal->replaces_proposal_id = $replaced->id;
                $proposal->revision_number = max($revisionNumber, $replaced->revision_number + 1);
            }

            $proposal->save();

            foreach ($normalizedItems as $index => $item) {
                $proposalItem = new BarterProposalItem();
                $proposalItem->proposal_id = $proposal->id;
                $proposalItem->owner_user_id = $item['owner_user_id'];
                $proposalItem->asset_type = $item['asset_type'];
                $proposalItem->asset_id = $item['asset_id'];
                $proposalItem->position = $index;
                $proposalItem->snapshot = $item['snapshot'];
                $proposalItem->save();
            }

            return $proposal->load(['items.ownerUser', 'proposer', 'counterparty', 'acceptedBy']);
        });
    }

    public function listThreadAssets(User $actor, string $threadType, int $threadId, int $counterpartyUserId): array
    {
        if ($threadId < 1) {
            throw new ValidationException(['thread_id' => 'Thread ID is required.']);
        }

        if ($counterpartyUserId < 1 || $counterpartyUserId === $actor->id) {
            throw new ValidationException(['counterparty_user_id' => 'A valid counterparty is required.']);
        }

        $threadType = trim($threadType) ?: BarterProposal::THREAD_DIALOG;
        $participants = $this->resolveThreadParticipants($threadType, $threadId);

        if (! in_array($actor->id, $participants, true) || ! in_array($counterpartyUserId, $participants, true)) {
            throw new ValidationException(['thread' => 'Both users must belong to the target private message thread.']);
        }

        return [
            'threadType' => $threadType,
            'threadId' => $threadId,
            'actorUserId' => $actor->id,
            'counterpartyUserId' => $counterpartyUserId,
            'yours' => [
                'collectibles' => Collectible::query()
                    ->where('owner_id', $actor->id)
                    ->where('status', Collectible::STATUS_COMPLETED)
                    ->orderByDesc('created_at')
                    ->get()
                    ->map(static fn (Collectible $collectible) => BarterAssetFormatter::collectible($collectible))
                    ->values()
                    ->all(),
                'blindBoxes' => BlindBox::query()
                    ->where('user_id', $actor->id)
                    ->whereIn('status', [BlindBox::STATUS_UNAPPRAISED, BlindBox::STATUS_APPRAISED])
                    ->orderByDesc('created_at')
                    ->get()
                    ->map(static fn (BlindBox $box) => BarterAssetFormatter::blindBox($box))
                    ->values()
                    ->all(),
            ],
            'theirs' => [
                'collectibles' => Collectible::query()
                    ->where('owner_id', $counterpartyUserId)
                    ->where('status', Collectible::STATUS_COMPLETED)
                    ->orderByDesc('created_at')
                    ->get()
                    ->map(static fn (Collectible $collectible) => BarterAssetFormatter::collectible($collectible))
                    ->values()
                    ->all(),
                'blindBoxes' => BlindBox::query()
                    ->where('user_id', $counterpartyUserId)
                    ->whereIn('status', [BlindBox::STATUS_UNAPPRAISED, BlindBox::STATUS_APPRAISED])
                    ->orderByDesc('created_at')
                    ->get()
                    ->map(static fn (BlindBox $box) => BarterAssetFormatter::blindBox($box))
                    ->values()
                    ->all(),
            ],
        ];
    }

    public function acceptProposal(BarterProposal $proposal, User $actor): BarterProposal
    {
        if ($proposal->status !== BarterProposal::STATUS_PROPOSED) {
            throw new ValidationException(['proposal' => 'This barter proposal is no longer open.']);
        }

        if ($proposal->counterparty_user_id !== $actor->id) {
            throw new ValidationException(['proposal' => 'Only the counterparty can accept this barter proposal.']);
        }

        $proposal = $this->db->transaction(function () use ($proposal, $actor) {
            /** @var BarterProposal $proposal */
            $proposal = BarterProposal::query()->where('id', $proposal->id)->lockForUpdate()->firstOrFail();

            if ($proposal->status !== BarterProposal::STATUS_PROPOSED) {
                throw new ValidationException(['proposal' => 'This barter proposal is no longer open.']);
            }

            $proposal->accepted_by_user_id = $actor->id;
            $this->stateMachines->get($proposal, 'barterProposal')->apply('accept');
            $this->stateMachines->get($proposal, 'barterProposal')->apply('settle');
            $proposal->save();

            return $proposal->load('items');
        });

        try {
            $proposal = $this->db->transaction(function () use ($proposal) {
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

                $this->stateMachines->get($proposal, 'barterProposal')->apply('complete');
                $proposal->save();

                return $proposal->load(['items.ownerUser', 'proposer', 'counterparty', 'acceptedBy']);
            });
        } catch (\Throwable $exception) {
            $proposal = $this->db->transaction(function () use ($proposal) {
                /** @var BarterProposal $proposal */
                $proposal = BarterProposal::query()->where('id', $proposal->id)->lockForUpdate()->firstOrFail();

                if ($proposal->status === BarterProposal::STATUS_SETTLING) {
                    $this->stateMachines->get($proposal, 'barterProposal')->apply('fail');
                    $proposal->save();
                }

                return $proposal->load(['items.ownerUser', 'proposer', 'counterparty', 'acceptedBy']);
            });

            throw $exception;
        }

        return $proposal;
    }

    public function rejectProposal(BarterProposal $proposal, User $actor): BarterProposal
    {
        if ($proposal->status !== BarterProposal::STATUS_PROPOSED) {
            throw new ValidationException(['proposal' => 'This barter proposal is no longer open.']);
        }

        if ($proposal->counterparty_user_id !== $actor->id) {
            throw new ValidationException(['proposal' => 'Only the counterparty can reject this barter proposal.']);
        }

        $this->stateMachines->get($proposal, 'barterProposal')->apply('reject');
        $proposal->save();

        return $proposal->load(['items.ownerUser', 'proposer', 'counterparty', 'acceptedBy']);
    }

    public function cancelProposal(BarterProposal $proposal, User $actor): BarterProposal
    {
        if ($proposal->status !== BarterProposal::STATUS_PROPOSED) {
            throw new ValidationException(['proposal' => 'This barter proposal is no longer open.']);
        }

        if ($proposal->proposer_user_id !== $actor->id) {
            throw new ValidationException(['proposal' => 'Only the proposer can cancel this barter proposal.']);
        }

        $this->stateMachines->get($proposal, 'barterProposal')->apply('cancel');
        $proposal->save();

        return $proposal->load(['items.ownerUser', 'proposer', 'counterparty', 'acceptedBy']);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array{owner_user_id: int, asset_type: string, asset_id: int, snapshot: array<string, mixed>}>
     */
    private function normalizeItems(array $items, int $actorUserId, int $counterpartyUserId): array
    {
        $normalized = [];
        $seenAssets = [];
        $owners = [];

        foreach ($items as $index => $item) {
            $ownerUserId = (int) Arr::get($item, 'ownerUserId');
            $assetType = (string) Arr::get($item, 'assetType');
            $assetId = (int) Arr::get($item, 'assetId');

            if ($ownerUserId < 1 || ! in_array($ownerUserId, [$actorUserId, $counterpartyUserId], true)) {
                throw new ValidationException(['items' => 'Every barter item must belong to either the proposer or the counterparty.']);
            }

            if ($assetId < 1) {
                throw new ValidationException(['items' => 'Every barter item needs a valid asset ID.']);
            }

            if (! in_array($assetType, [BarterProposal::ASSET_COLLECTIBLE, BarterProposal::ASSET_BLIND_BOX], true)) {
                throw new ValidationException(['items' => 'Unsupported barter asset type: ' . $assetType]);
            }

            $assetKey = $assetType . ':' . $assetId;
            if (isset($seenAssets[$assetKey])) {
                throw new ValidationException(['items' => 'Duplicate assets are not allowed in one barter proposal.']);
            }
            $seenAssets[$assetKey] = true;

            $owners[$ownerUserId] = true;

            $normalized[] = [
                'owner_user_id' => $ownerUserId,
                'asset_type' => $assetType,
                'asset_id' => $assetId,
                'snapshot' => $assetType === BarterProposal::ASSET_COLLECTIBLE
                    ? $this->snapshotCollectible($assetId, $ownerUserId)
                    : $this->snapshotBlindBox($assetId, $ownerUserId),
            ];
        }

        if (! isset($owners[$actorUserId]) || ! isset($owners[$counterpartyUserId])) {
            throw new ValidationException(['items' => 'A barter proposal must include assets from both sides.']);
        }

        return $normalized;
    }

    /**
     * @return array<int, int>
     */
    private function resolveThreadParticipants(string $threadType, int $threadId): array
    {
        if ($threadType !== BarterProposal::THREAD_DIALOG) {
            return [];
        }

        if (! $this->db instanceof Connection) {
            return [];
        }

        $schema = $this->db->getSchemaBuilder();
        if (! $schema->hasTable('dialogs') || ! $schema->hasTable('dialog_user')) {
            return [];
        }

        $dialog = $this->db->table('dialogs')->where('id', $threadId)->first();

        if (! $dialog) {
            throw new ValidationException(['thread' => 'Private message thread not found.']);
        }

        if (($dialog->type ?? null) !== 'direct') {
            throw new ValidationException(['thread' => 'Only direct private message dialogs are supported for barter.']);
        }

        $userIds = $this->db->table('dialog_user')
            ->where('dialog_id', $threadId)
            ->pluck('user_id')
            ->map(static fn ($userId) => (int) $userId)
            ->all();

        $userIds = array_values(array_unique($userIds));

        if (count($userIds) !== 2) {
            throw new ValidationException(['thread' => 'Barter currently requires a two-user private message dialog.']);
        }

        return $userIds;
    }

    private function nextRevisionNumber(string $threadType, int $threadId): int
    {
        return (int) BarterProposal::query()
            ->where('thread_type', $threadType)
            ->where('thread_id', $threadId)
            ->max('revision_number') + 1;
    }

    private function proposalInvolvesUser(BarterProposal $proposal, int $userId): bool
    {
        return $proposal->proposer_user_id === $userId || $proposal->counterparty_user_id === $userId;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotCollectible(int $collectibleId, int $ownerUserId): array
    {
        /** @var Collectible $collectible */
        $collectible = Collectible::query()
            ->where('id', $collectibleId)
            ->where('owner_id', $ownerUserId)
            ->where('status', Collectible::STATUS_COMPLETED)
            ->firstOrFail();

        return BarterAssetFormatter::collectible($collectible);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotBlindBox(int $boxId, int $ownerUserId): array
    {
        /** @var BlindBox $box */
        $box = BlindBox::query()
            ->where('id', $boxId)
            ->where('user_id', $ownerUserId)
            ->whereIn('status', [BlindBox::STATUS_UNAPPRAISED, BlindBox::STATUS_APPRAISED])
            ->firstOrFail();

        return BarterAssetFormatter::blindBox($box);
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
