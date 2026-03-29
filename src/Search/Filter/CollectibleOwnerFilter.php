<?php

namespace Donk\AigcCollectibles\Search\Filter;

use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\Filter\FilterInterface;
use Flarum\Search\SearchState;
use Flarum\Search\ValidateFilterTrait;

/**
 * @implements FilterInterface<DatabaseSearchState>
 */
class CollectibleOwnerFilter implements FilterInterface
{
    use ValidateFilterTrait;

    public function getFilterKey(): string
    {
        return 'owner';
    }

    public function filter(SearchState $state, string|array $value, bool $negate): void
    {
        $ownerIds = $this->asIntArray($value);

        $state->getQuery()->whereIn('collectibles.owner_id', $ownerIds, 'and', $negate);
    }
}
