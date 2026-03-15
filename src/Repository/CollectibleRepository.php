<?php

namespace Donk\AigcCollectibles\Repository;

use Donk\AigcCollectibles\Model\Collectible;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CollectibleRepository
{
    public function query(): Builder
    {
        return Collectible::query();
    }

    public function findOrFail(int $id, ?User $actor = null): Collectible
    {
        $query = $this->query()->where('id', $id);

        if ($actor) {
            $query->whereVisibleTo($actor);
        }

        return $query->firstOrFail();
    }

    public function findByUser(User $user, ?User $actor = null): Collection
    {
        $query = $this->query()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc');

        if ($actor) {
            $query->whereVisibleTo($actor);
        }

        return $query->get();
    }

    public function findByOriginalUser(User $user): Collection
    {
        return $this->query()
            ->where('original_user_id', $user->id)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
