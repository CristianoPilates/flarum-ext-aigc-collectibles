<?php

namespace Donk\AigcCollectibles\Service;

use Carbon\Carbon;
use Donk\AigcCollectibles\Event\TradeCompleted;
use Donk\AigcCollectibles\Event\TradeCreated;
use Donk\AigcCollectibles\Model\Collectible;
use Donk\AigcCollectibles\Model\CollectibleEvent;
use Donk\AigcCollectibles\Model\Trade;
use Donk\AigcCollectibles\Service\Contracts\BlindBoxServiceInterface;
use Donk\AigcCollectibles\Service\Contracts\TradeServiceInterface;
use Flarum\Foundation\ValidationException;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

/**
 * Handles P2P collectible trading between users.
 *
 * Trades use blind boxes as currency: a buyer offers N blind boxes
 * for a seller's specific collectible. The seller can accept, reject,
 * or the buyer can cancel while the trade is still pending.
 */
class TradeService implements TradeServiceInterface
{
    public function __construct(
        protected BlindBoxServiceInterface $blindBoxService,
        protected ConnectionInterface $db,
        protected Dispatcher $events
    ) {}

    /**
     * Create a new trade offer from a buyer for a specific collectible.
     *
     * Validates that the collectible exists and is completed, that the
     * buyer is not the current owner, and that the buyer has sufficient
     * blind boxes to cover the offer.
     *
     * @param  User    $buyer        The user initiating the trade offer.
     * @param  int     $collectibleId  ID of the target collectible.
     * @param  int     $offeredBoxes   Number of blind boxes offered.
     * @param  string|null $note      Optional message to the seller.
     *
     * @return Trade The persisted trade with status "pending".
     *
     * @throws ValidationException If the buyer owns the collectible or has insufficient blind boxes.
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If the collectible or seller does not exist.
     */
    public function createOffer(User $buyer, int $collectibleId, int $offeredBoxes, ?string $note = null): Trade
    {
        $collectible = Collectible::query()
            ->where('id', $collectibleId)
            ->where('status', 'completed')
            ->firstOrFail();

        $seller = User::query()
            ->where('id', $collectible->user_id)
            ->firstOrFail();

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

    /**
     * Accept a pending trade as the collectible owner (seller).
     *
     * Runs inside a database transaction with row-level locking to
     * prevent race conditions. Transfers blind boxes from buyer to
     * seller, reassigns collectible ownership, cancels all other
     * pending trades for the same collectible, and logs a
     * {@see CollectibleEvent}.
     *
     * @param  Trade $trade The pending trade to accept.
     * @param  User  $actor The authenticated user (must be the seller).
     *
     * @return Trade The accepted trade with updated status and completed_at.
     *
     * @throws ValidationException If the trade is not pending, the actor is not
     *                             the seller, or the collectible ownership changed.
     */
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

    /**
     * Reject a pending trade as the collectible owner (seller).
     *
     * Sets the trade status to "rejected", records the completion
     * timestamp, and dispatches a {@see TradeCompleted} event.
     *
     * @param  Trade $trade The pending trade to reject.
     * @param  User  $actor The authenticated user (must be the seller).
     *
     * @return Trade The rejected trade.
     *
     * @throws ValidationException If the trade is not pending or the actor is not the seller.
     */
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

    /**
     * Cancel a pending trade as the trade initiator (buyer).
     *
     * Sets the trade status to "cancelled", records the completion
     * timestamp, and dispatches a {@see TradeCompleted} event.
     *
     * @param  Trade $trade The pending trade to cancel.
     * @param  User  $actor The authenticated user (must be the buyer).
     *
     * @return Trade The cancelled trade.
     *
     * @throws ValidationException If the trade is not pending or the actor is not the buyer.
     */
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
