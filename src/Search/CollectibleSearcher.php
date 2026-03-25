<?php

namespace Donk\AigcCollectibles\Search;

use Donk\AigcCollectibles\Model\Collectible;
use Flarum\Search\Database\AbstractSearcher;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

class CollectibleSearcher extends AbstractSearcher
{
    public function getQuery(User $actor): Builder
    {
        return Collectible::query()
            ->whereVisibleTo($actor)
            ->select('collectibles.*');
    }
}
