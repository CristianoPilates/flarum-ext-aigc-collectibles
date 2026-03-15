<?php

namespace Donk\AigcCollectibles\Service;

use Carbon\Carbon;
use Donk\AigcCollectibles\Event\TradeCompleted;
use Donk\AigcCollectibles\Event\TradeCreated;
use Donk\AigcCollectibles\Model\Collectible;
use Donk\AigcCollectibles\Model\CollectibleEvent;
use Donk\AigcCollectibles\Model\Trade;
use Flarum\Foundation\ValidationException;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

class TradeService
{
    protected BlindBoxService $blindBoxService;
    protected ConnectionInterface $db;
    protected Dispatcher $events;

    public function __construct(
        BlindBoxService $blindBoxService,
        ConnectionInterface $db,
        Dispatcher $events
    ) {
        $this->blindBoxService = $blindBoxService;
        $this->db = $db;
        $this->events = $events;
    }

    public function createOffer(User $buyer, int $collectibleId, int $offeredBoxes, ?string $note = null): Trade
    {
        $collectible = Collectible::query()
            ->where('id', $collectibleId)
            ->where('status', 'completed')
            ->firstOrFail();

        $seller = User::findOrFail($collectible->user_id);

        if ($buyer->id === $seller->id) {
            throw new ValidationException([
                'trade' => 'You cannot trade with yourself.',
            ]);
        }

        if ($this->blindBoxService->balanceOf($buyer) < $offeredBoxes) {
            throw new ValidationException([
                'offered_boxes' => 'Insufficient blind boxes.',
            ]);
        }

        $trade = Trade::createOffer($buyer, $seller, $collectible, $offeredBoxes, $note);
        $trade->save();

        $this->events->dispatch(new TradeCreated($buyer, $trade));

        return $trade;
    }

    public function acceptTrade(Trade $trade, User $actor): Trade
    {
        if ($trade->status !== 'pending') {
            throw new ValidationException([
                'trade' => 'This trade is no longer pending.',
            ]);
        }

        if ($trade->to_user_id !== $actor->id) {
            throw new ValidationException([
                'trade' => 'Only the collectible owner can accept this trade.',
            ]);
        }

        return $this->db->transaction(function () use ($trade, $actor) {
            $buyer = User::query()
                ->where('id', $trade->from_user_id)
                ->lockForUpdate()
                ->firstOrFail();

            $seller = User::query()
                ->where('id', $trade->to_user_id)
                ->lockForUpdate()
                ->firstOrFail();

            $collectible = Collectible::query()
                ->where('id', $trade->collectible_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($collectible->user_id !== $seller->id) {
                throw new ValidationException([
                    'trade' => 'The collectible is no longer owned by the seller.',
                ]);
            }

            $this->blindBoxService->transfer($buyer, $seller, $trade->offered_boxes);

            $collectible->user_id = $buyer->id;
            $collectible->times_traded += 1;
            $collectible->save();

            $trade->status = 'accepted';
            $trade->completed_at = Carbon::now();
            $trade->save();

            Trade::query()
                ->where('collectible_id', $trade->collectible_id)
                ->where('id', '!=', $trade->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'cancelled',
                    'updated_at' => Carbon::now(),
                ]);

            $event = CollectibleEvent::log(
                $collectible,
                'traded',
                $seller->id,
                $buyer->id,
                $trade->id
            );
            $event->save();

            $this->events->dispatch(new TradeCompleted($actor, $trade, 'accepted'));

            return $trade;
        });
    }

    public function rejectTrade(Trade $trade, User $actor): Trade
    {
        if ($trade->status !== 'pending') {
            throw new ValidationException([
                'trade' => 'This trade is no longer pending.',
            ]);
        }

        if ($trade->to_user_id !== $actor->id) {
            throw new ValidationException([
                'trade' => 'Only the collectible owner can reject this trade.',
            ]);
        }

        $trade->status = 'rejected';
        $trade->completed_at = Carbon::now();
        $trade->save();

        $this->events->dispatch(new TradeCompleted($actor, $trade, 'rejected'));

        return $trade;
    }

    public function cancelTrade(Trade $trade, User $actor): Trade
    {
        if ($trade->status !== 'pending') {
            throw new ValidationException([
                'trade' => 'This trade is no longer pending.',
            ]);
        }

        if ($trade->from_user_id !== $actor->id) {
            throw new ValidationException([
                'trade' => 'Only the trade initiator can cancel this trade.',
            ]);
        }

        $trade->status = 'cancelled';
        $trade->completed_at = Carbon::now();
        $trade->save();

        $this->events->dispatch(new TradeCompleted($actor, $trade, 'cancelled'));

        return $trade;
    }
}
