<?php

namespace Donk\AigcCollectibles\Api\Resource;

use Donk\AigcCollectibles\Command\AppraiseBlindBox;
use Donk\AigcCollectibles\Command\OpenBlindBox;
use Donk\AigcCollectibles\Model\BlindBox;
use Donk\AigcCollectibles\Service\Contracts\BlindBoxServiceInterface;
use Donk\AigcCollectibles\Support\DirectDialogParticipants;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Flarum\Foundation\ValidationException;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Support\Arr;
use Tobyz\JsonApiServer\Context;

/**
 * @extends AbstractDatabaseResource<BlindBox>
 */
class BlindBoxResource extends AbstractDatabaseResource
{
    public function __construct(
        private readonly BusDispatcher $bus,
        private readonly BlindBoxServiceInterface $blindBoxService,
        private readonly DirectDialogParticipants $dialogs,
    ) {}

    public function type(): string
    {
        return 'blindboxes';
    }

    public function model(): string
    {
        return BlindBox::class;
    }

    public function query(Context $context): object
    {
        $query = parent::query($context);
        $queryParams = $context->request->getQueryParams();

        if ($queryParams === []) {
            parse_str($context->request->getUri()->getQuery(), $queryParams);
        }

        $filters = is_array($queryParams['filter'] ?? null) ? $queryParams['filter'] : [];
        $requestedUserId = $filters['user'] ?? $filters['owner'] ?? null;
        $ownerUserId = is_numeric($requestedUserId) ? (int) $requestedUserId : (int) $context->getActor()->id;

        if ($ownerUserId !== (int) $context->getActor()->id) {
            $dialogId = $filters['dialog'] ?? null;

            if (! is_numeric($dialogId) || (int) $dialogId < 1) {
                throw new ValidationException(['dialog' => 'A direct private message dialog is required when loading another user\'s blind boxes.']);
            }

            $this->dialogs->assertContainsUsers((int) $dialogId, (int) $context->getActor()->id, $ownerUserId, 'dialog');
        }

        return $query->where('user_id', $ownerUserId);
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->authenticated()
                ->defaultSort('-createdAt')
                ->paginate(),

            Endpoint\Show::make()
                ->authenticated(),

            Endpoint\Endpoint::make('appraise')
                ->route('POST', '/{id}/appraise')
                ->authenticated()
                ->action(function (Context $context) {
                    $body = $context->body();

                    return $this->bus->dispatch(
                        new AppraiseBlindBox(
                            actor: $context->getActor(),
                            boxId: intval($context->modelId),
                            nonce: Arr::get($body, 'nonce', ''),
                            hash: Arr::get($body, 'hash', ''),
                        )
                    );
                }),

            Endpoint\Endpoint::make('open')
                ->route('POST', '/{id}/open')
                ->authenticated()
                ->action(function (Context $context) {
                    return $this->bus->dispatch(
                        new OpenBlindBox(
                            actor: $context->getActor(),
                            boxId: intval($context->modelId),
                        )
                    );
                }),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('type'),
            Schema\Str::make('seed'),
            Schema\Str::make('status'),
            Schema\Integer::make('budget'),
            Schema\Arr::make('drawRules')
                ->get(fn (BlindBox $model) => $this->blindBoxService->describeDrawRules($model->type)),
            Schema\DateTime::make('createdAt'),
            Schema\DateTime::make('updatedAt'),

            Schema\Relationship\ToOne::make('user')
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
            SortColumn::make('updatedAt'),
            SortColumn::make('status'),
        ];
    }
}
