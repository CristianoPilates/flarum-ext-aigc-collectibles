<?php

namespace Donk\AigcCollectibles\Api\Resource;

use Donk\AigcCollectibles\Command\AppraiseBlindBox;
use Donk\AigcCollectibles\Command\OpenBlindBox;
use Donk\AigcCollectibles\Model\BlindBox;
use Donk\AigcCollectibles\Model\BlindBoxDrawRule;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Flarum\Foundation\ValidationException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Support\Arr;
use Tobyz\JsonApiServer\Context;

/**
 * @extends AbstractDatabaseResource<BlindBox>
 */
class BlindBoxResource extends AbstractDatabaseResource
{
    private const DRAW_RULE_FALLBACKS = [
        'trade_reward' => 'checkin_reward',
    ];

    public function __construct(
        private readonly BusDispatcher $bus,
        private readonly ConnectionInterface $db,
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

            $this->assertDialogParticipants((int) $dialogId, (int) $context->getActor()->id, $ownerUserId);
        }

        return $query->where('user_id', $ownerUserId);
    }

    private function assertDialogParticipants(int $dialogId, int $actorUserId, int $ownerUserId): void
    {
        $schema = $this->db->getSchemaBuilder();

        if (! $schema->hasTable('dialogs') || ! $schema->hasTable('dialog_user')) {
            throw new ValidationException(['dialog' => 'Private messages are not available in this environment.']);
        }

        $dialog = $this->db->table('dialogs')->where('id', $dialogId)->first();

        if (! $dialog) {
            throw new ValidationException(['dialog' => 'Private message dialog not found.']);
        }

        if (($dialog->type ?? null) !== 'direct') {
            throw new ValidationException(['dialog' => 'Only direct private message dialogs can expose barter blind boxes.']);
        }

        $participantIds = $this->db->table('dialog_user')
            ->where('dialog_id', $dialogId)
            ->pluck('user_id')
            ->map(static fn ($userId) => (int) $userId)
            ->all();

        $participantIds = array_values(array_unique($participantIds));

        if (
            count($participantIds) !== 2
            || ! in_array($actorUserId, $participantIds, true)
            || ! in_array($ownerUserId, $participantIds, true)
        ) {
            throw new ValidationException(['dialog' => 'Both users must belong to the same direct private message dialog.']);
        }
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
                ->get(function (BlindBox $model) {
                    static $ruleCache = [];
                    $ruleType = self::DRAW_RULE_FALLBACKS[$model->type] ?? $model->type;

                    if (! array_key_exists($ruleType, $ruleCache)) {
                        $ruleCache[$ruleType] = BlindBoxDrawRule::query()
                            ->where('blindbox_type', $ruleType)
                            ->orderByDesc('required')
                            ->orderBy('pool_category')
                            ->get(['pool_category', 'required'])
                            ->map(fn (BlindBoxDrawRule $rule) => [
                                'category' => $rule->pool_category,
                                'required' => (bool) $rule->required,
                            ])
                            ->values()
                            ->all();
                    }

                    return $ruleCache[$ruleType];
                }),
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
