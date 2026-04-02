<?php

namespace Donk\AigcCollectibles\Api\Resource;

use Donk\AigcCollectibles\Command\MintCollectible;
use Donk\AigcCollectibles\Model\Collectible;
use Donk\AigcCollectibles\Repository\CollectibleRepository;
use Donk\AigcCollectibles\Service\Contracts\CollectibleProofServiceInterface;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Flarum\Bus\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laminas\Diactoros\Response\JsonResponse;

/**
 * @extends AbstractDatabaseResource<Collectible>
 */
class CollectibleResource extends AbstractDatabaseResource
{
    public function __construct(
        protected Dispatcher $bus,
        protected CollectibleRepository $collectibles,
        protected CollectibleProofServiceInterface $proofs,
    ) {
    }

    public function type(): string
    {
        return 'collectibles';
    }

    public function model(): string
    {
        return Collectible::class;
    }

    public function scope(Builder $query, \Tobyz\JsonApiServer\Context $context): void
    {
        $query->whereVisibleTo($context->getActor());

        $queryParams = $context->request->getQueryParams();
        $filters = $queryParams['filter'] ?? [];
        $ownerId = $queryParams['owner'] ?? $queryParams['user'] ?? $filters['owner'] ?? $filters['user'] ?? null;

        if ($ownerId !== null && is_numeric($ownerId)) {
            $query->where('owner_id', (int) $ownerId);
        }
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->paginate(20, 50)
                ->defaultSort('-createdAt')
                ->defaultInclude(['owner']),

            Endpoint\Show::make()
                ->defaultInclude(['owner']),

            Endpoint\Endpoint::make('mint')
                ->route('POST', '/{id}/mint')
                ->authenticated()
                ->action(function (Context $context) {
                    return $this->bus->dispatch(
                        new MintCollectible((int) $context->modelId, $context->getActor())
                    );
                }),

            Endpoint\Endpoint::make('proof')
                ->route('GET', '/{id}/proof')
                ->action(function (Context $context) {
                    $collectible = $this->collectibles->query()->findOrFail((int) $context->modelId);
                    $actor = $context->getActor();

                    if ($collectible->status !== Collectible::STATUS_COMPLETED && $actor->id !== $collectible->owner_id) {
                        throw new ModelNotFoundException();
                    }

                    return $this->proofs->buildProof($collectible);
                })
                ->response(function (Context $context, array $proof) {
                    return new JsonResponse([
                        'data' => [
                            'type' => 'collectible-proof',
                            'id' => (string) $context->modelId,
                            'attributes' => $proof,
                        ],
                    ]);
                }),

            Endpoint\Update::make()
                ->authenticated(),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('name'),
            Schema\Str::make('rarity'),
            Schema\Str::make('status'),
            Schema\Str::make('ipfsCid')
                ->property('ipfs_cid'),
            Schema\Str::make('metadataCid')
                ->property('metadata_cid'),
            Schema\Str::make('aigcPrompt')
                ->property('aigc_prompt')
                ->visible(fn (Collectible $model, Context $context) => $context->getActor()->id === $model->owner_id),
            Schema\Integer::make('tokenId')
                ->property('token_id')
                ->nullable(),
            Schema\Integer::make('timesTraded')
                ->property('times_traded'),
            Schema\Boolean::make('canMint')
                ->visible(fn (Collectible $model, Context $context) => $context->getActor()->id === $model->owner_id)
                ->get(fn (Collectible $model, Context $context) => $context->getActor()->can('mint', $model)),
            Schema\Boolean::make('isShowcase')
                ->get(fn (Collectible $model, Context $context) => $context->getActor()->showcase_collectible_id === $model->id)
                ->writable(fn () => true)
                ->save(fn () => null),
            Schema\DateTime::make('createdAt')
                ->property('created_at'),
            Schema\DateTime::make('updatedAt')
                ->property('updated_at'),

            Schema\Relationship\ToOne::make('owner')
                ->type('users')
                ->includable(),
            Schema\Relationship\ToMany::make('events')
                ->type('collectible-events')
                ->includable(),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('createdAt'),
            SortColumn::make('rarity'),
            SortColumn::make('timesTraded'),
        ];
    }

    public function updating(object $model, \Tobyz\JsonApiServer\Context $context): ?object
    {
        $data = $context->body();
        $isShowcase = $data['data']['attributes']['isShowcase'] ?? null;

        if ($isShowcase !== null) {
            $actor = $context->getActor();
            $actor->assertCan('showcase', $model);

            if ($isShowcase) {
                $actor->showcase_collectible_id = $model->id;
            } else {
                $actor->showcase_collectible_id = null;
            }

            $actor->save();
        }

        return $model;
    }
}
