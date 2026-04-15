<?php

namespace Donk\AigcCollectibles\Api\Resource;

use Donk\AigcCollectibles\Model\BarterProposalItem;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;

/**
 * @extends AbstractDatabaseResource<BarterProposalItem>
 */
class BarterProposalItemResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'barter-proposal-items';
    }

    public function model(): string
    {
        return BarterProposalItem::class;
    }

    public function endpoints(): array
    {
        return [];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('assetType')
                ->property('asset_type'),
            Schema\Integer::make('assetId')
                ->property('asset_id'),
            Schema\Integer::make('ownerUserId')
                ->property('owner_user_id'),
            Schema\Integer::make('position'),
            Schema\Arr::make('snapshot')
                ->nullable(),
            Schema\DateTime::make('createdAt')
                ->property('created_at'),
            Schema\DateTime::make('updatedAt')
                ->property('updated_at'),

            Schema\Relationship\ToOne::make('ownerUser')
                ->type('users')
                ->includable(),
        ];
    }
}
