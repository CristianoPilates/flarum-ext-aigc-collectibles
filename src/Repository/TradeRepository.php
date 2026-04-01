<?php

namespace Donk\AigcCollectibles\Repository;

use Donk\AigcCollectibles\Model\Trade;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class TradeRepository
{
    /**
     * @return Builder<Trade>
     */
    public function query(): Builder
    {
        return Trade::query();
    }

    public function findOrFail(int $id, ?User $actor = null): Trade
    {
        $query = $this->query()->where('id', $id);

        if ($actor) {
            $query->whereVisibleTo($actor);
        }

        return $query->firstOrFail();
    }

    /**
     * @return Collection<int, Trade>
     */
    public function findByUser(User $user): Collection
    {
        return $this->query()
            ->where(function (Builder $query) use ($user) {
                $query->where('from_user_id', $user->id)
                    ->orWhere('to_user_id', $user->id);
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * @return Collection<int, Trade>
     */
    public function findPendingForCollectible(int $collectibleId): Collection
    {
        return $this->query()
            ->where('collectible_id', $collectibleId)
            ->where('status', Trade::STATUS_PENDING)
            ->get();
    }
}
