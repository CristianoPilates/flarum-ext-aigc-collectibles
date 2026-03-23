<?php

namespace Donk\AigcCollectibles\Api\Resource;

use Donk\AigcCollectibles\Command\AcceptTrade;
use Donk\AigcCollectibles\Command\CancelTrade;
use Donk\AigcCollectibles\Command\CreateTrade;
use Donk\AigcCollectibles\Command\RejectTrade;
use Donk\AigcCollectibles\Model\Trade;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Flarum\Bus\Dispatcher;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends AbstractDatabaseResource<Trade>
 */
class TradeResource extends AbstractDatabaseResource
{
    public function __construct(
        protected Dispatcher $bus,
    ) {
    }

    public function type(): string
    {
        return 'trades';
    }

    public function model(): string
    {
        return Trade::class;
    }

    public function scope(Builder $query, \Tobyz\JsonApiServer\Context $context): void
    {
        $actor = $context->getActor();

        // Users can only see trades they are involved in
        $query->where(function (Builder $query) use ($actor) {
            $query->where('from_user_id', $actor->id)
                ->orWhere('to_user_id', $actor->id);
        });
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->authenticated()
                ->paginate(20, 50)
                ->defaultSort('-createdAt')
                ->defaultInclude(['collectible', 'fromUser', 'toUser']),

            Endpoint\Create::make()
                ->authenticated()
                ->action(function (Context $context) {
                    return $this->bus->dispatch(
                        new CreateTrade($context->getActor(), $context->body())
                    );
                }),

            Endpoint\Endpoint::make('accept')
                ->route('POST', '/{id}/accept')
                ->authenticated()
                ->action(function (Context $context) {
                    return $this->bus->dispatch(
                        new AcceptTrade((int) $context->modelId, $context->getActor())
                    );
                }),

            Endpoint\Endpoint::make('reject')
                ->route('POST', '/{id}/reject')
                ->authenticated()
                ->action(function (Context $context) {
                    return $this->bus->dispatch(
                        new RejectTrade((int) $context->modelId, $context->getActor())
                    );
                }),

            Endpoint\Endpoint::make('cancel')
                ->route('DELETE', '/{id}')
                ->authenticated()
                ->action(function (Context $context) {
                    $this->bus->dispatch(
                        new CancelTrade((int) $context->modelId, $context->getActor())
                    );

                    return null;
                })
                ->response(fn () => new \Nyholm\Psr7\Response(204)),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Integer::make('offeredBoxes')
                ->property('offered_boxes'),
            Schema\Str::make('status'),
            Schema\Str::make('note')
                ->nullable(),
            Schema\Boolean::make('canAccept')
                ->get(fn (Trade $model, Context $context) => $context->getActor()->can('accept', $model)),
            Schema\Boolean::make('canReject')
                ->get(fn (Trade $model, Context $context) => $context->getActor()->can('reject', $model)),
            Schema\Boolean::make('canCancel')
                ->get(fn (Trade $model, Context $context) => $context->getActor()->can('cancel', $model)),
            Schema\DateTime::make('completedAt')
                ->property('completed_at')
                ->nullable(),
            Schema\DateTime::make('createdAt')
                ->property('created_at'),
            Schema\DateTime::make('updatedAt')
                ->property('updated_at'),

            Schema\Relationship\ToOne::make('fromUser')
                ->type('users')
                ->includable(),
            Schema\Relationship\ToOne::make('toUser')
                ->type('users')
                ->includable(),
            Schema\Relationship\ToOne::make('collectible')
                ->type('collectibles')
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
