<?php

namespace Donk\AigcCollectibles\Api\Resource;

use Donk\AigcCollectibles\Command\AcceptBarterProposal;
use Donk\AigcCollectibles\Command\CancelBarterProposal;
use Donk\AigcCollectibles\Command\CreateBarterProposal;
use Donk\AigcCollectibles\Command\RejectBarterProposal;
use Donk\AigcCollectibles\Model\BarterProposal;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Flarum\Bus\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Laminas\Diactoros\Response\EmptyResponse;

/**
 * @extends AbstractDatabaseResource<BarterProposal>
 */
class BarterProposalResource extends AbstractDatabaseResource
{
    public function __construct(
        private readonly Dispatcher $bus,
    ) {}

    public function type(): string
    {
        return 'barter-proposals';
    }

    public function model(): string
    {
        return BarterProposal::class;
    }

    public function scope(Builder $query, \Tobyz\JsonApiServer\Context $context): void
    {
        $actor = $context->getActor();

        $query->where(function (Builder $query) use ($actor) {
            $query->where('proposer_user_id', $actor->id)
                ->orWhere('counterparty_user_id', $actor->id);
        });

        $queryParams = $context->request->getQueryParams();
        $filters = is_array($queryParams['filter'] ?? null) ? $queryParams['filter'] : [];

        $threadType = $queryParams['threadType'] ?? $filters['threadType'] ?? null;
        $threadId = $queryParams['threadId'] ?? $filters['threadId'] ?? null;

        if (is_string($threadType) && $threadType !== '') {
            $query->where('thread_type', $threadType);
        }

        if (is_numeric($threadId)) {
            $query->where('thread_id', (int) $threadId);
        }
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->authenticated()
                ->defaultInclude(['items.ownerUser', 'proposer', 'counterparty', 'acceptedBy']),

            Endpoint\Show::make()
                ->authenticated()
                ->defaultInclude(['items.ownerUser', 'proposer', 'counterparty', 'acceptedBy']),

            Endpoint\Create::make()
                ->authenticated()
                ->action(function (Context $context) {
                    return $this->bus->dispatch(
                        new CreateBarterProposal($context->getActor(), $context->body())
                    );
                }),

            Endpoint\Endpoint::make('accept')
                ->route('POST', '/{id}/accept')
                ->authenticated()
                ->action(function (Context $context) {
                    return $this->bus->dispatch(
                        new AcceptBarterProposal((int) $context->modelId, $context->getActor())
                    );
                }),

            Endpoint\Endpoint::make('reject')
                ->route('POST', '/{id}/reject')
                ->authenticated()
                ->action(function (Context $context) {
                    return $this->bus->dispatch(
                        new RejectBarterProposal((int) $context->modelId, $context->getActor())
                    );
                }),

            Endpoint\Endpoint::make('cancel')
                ->route('POST', '/{id}/cancel')
                ->authenticated()
                ->action(function (Context $context) {
                    return $this->bus->dispatch(
                        new CancelBarterProposal((int) $context->modelId, $context->getActor())
                    );
                }),

            Endpoint\Delete::make()
                ->authenticated()
                ->visible(fn (BarterProposal $proposal, Context $context) => $context->getActor()->can('cancel', $proposal))
                ->action(function (Context $context) {
                    $this->bus->dispatch(
                        new CancelBarterProposal((int) $context->modelId, $context->getActor())
                    );

                    return null;
                })
                ->response(fn () => new EmptyResponse(204)),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('threadType')
                ->property('thread_type'),
            Schema\Integer::make('threadId')
                ->property('thread_id'),
            Schema\Integer::make('revisionNumber')
                ->property('revision_number'),
            Schema\Str::make('status'),
            Schema\Str::make('message')
                ->nullable(),
            Schema\Boolean::make('canAccept')
                ->get(fn (BarterProposal $proposal, Context $context) => $context->getActor()->can('accept', $proposal)),
            Schema\Boolean::make('canReject')
                ->get(fn (BarterProposal $proposal, Context $context) => $context->getActor()->can('reject', $proposal)),
            Schema\Boolean::make('canCancel')
                ->get(fn (BarterProposal $proposal, Context $context) => $context->getActor()->can('cancel', $proposal)),
            Schema\DateTime::make('completedAt')
                ->property('completed_at')
                ->nullable(),
            Schema\DateTime::make('createdAt')
                ->property('created_at'),
            Schema\DateTime::make('updatedAt')
                ->property('updated_at'),

            Schema\Relationship\ToOne::make('proposer')
                ->type('users')
                ->includable(),
            Schema\Relationship\ToOne::make('counterparty')
                ->type('users')
                ->includable(),
            Schema\Relationship\ToOne::make('acceptedBy')
                ->type('users')
                ->includable(),
            Schema\Relationship\ToOne::make('replacesProposal')
                ->type('barter-proposals')
                ->includable(),
            Schema\Relationship\ToMany::make('items')
                ->type('barter-proposal-items')
                ->includable(),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('createdAt'),
            SortColumn::make('updatedAt'),
            SortColumn::make('revisionNumber')
                ->column('revision_number'),
        ];
    }
}
