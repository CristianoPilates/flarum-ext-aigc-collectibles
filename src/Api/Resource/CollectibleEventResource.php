<?php

namespace Donk\AigcCollectibles\Api\Resource;

use Donk\AigcCollectibles\Model\CollectibleEvent;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;

/**
 * @extends AbstractDatabaseResource<CollectibleEvent>
 */
class CollectibleEventResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'collectible-events';
    }

    public function model(): string
    {
        return CollectibleEvent::class;
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->paginate(20, 50)
                ->defaultSort('-createdAt'),

            Endpoint\Show::make(),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('eventType')
                ->property('event_type'),
            Schema\Arr::make('metadata')
                ->nullable(),
            Schema\DateTime::make('createdAt')
                ->property('created_at'),

            Schema\Relationship\ToOne::make('collectible')
                ->type('collectibles')
                ->includable(),
            Schema\Relationship\ToOne::make('fromUser')
                ->type('users')
                ->includable(),
            Schema\Relationship\ToOne::make('toUser')
                ->type('users')
                ->includable(),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('createdAt'),
        ];
    }
}
