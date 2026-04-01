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
        return parent::query($context)
            ->where('user_id', $context->getActor()->id);
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
