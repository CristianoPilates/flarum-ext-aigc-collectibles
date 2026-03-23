<?php

namespace Donk\AigcCollectibles\Api\Resource;

use Donk\AigcCollectibles\Command\Checkin;
use Donk\AigcCollectibles\Model\CheckinRecord;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Serializer;
use Flarum\Bus\Dispatcher;

use function Tobyz\JsonApiServer\json_api_response;

/**
 * @extends AbstractDatabaseResource<CheckinRecord>
 */
class CheckinRecordResource extends AbstractDatabaseResource
{
    // Resource为什么需要一个Dispatcher $bus? 因为要用来触发签到事件
    public function __construct(
        protected Dispatcher $bus,
    ) {}

    // 这里是为了声明自己Resource的type, 会体现在/api/{type}里
    public function type(): string
    {
        return 'checkin-records';
    }

    // 与这个Resource绑定的model
    public function model(): string
    {
        return CheckinRecord::class;
    }

    // 这个Resource所在的位置, 即端点, 经过检查发现codebase里只有Resource里会用到Endpoint\Endpoint
    public function endpoints(): array
    {
        return [
            Endpoint\Endpoint::make('checkin')
                ->route('POST', '/checkin')
                ->authenticated()
                ->action(function (Context $context) {
                    return $this->bus->dispatch(
                        new Checkin($context->getActor())
                    );
                })
                ->response(function (Context $context, CheckinRecord $record) {
                    $serializer = new Serializer($context);
                    $serializer->addPrimary(
                        $context->resource($context->collection->resource($record, $context)),
                        $record,
                        []
                    );

                    [$primary, $included] = $serializer->serialize();
                    $document = ['data' => $primary[0]];

                    if (count($included)) {
                        $document['included'] = $included;
                    }

                    return json_api_response($document);
                }),
        ];
    }

    // Handler会返回一个CheckinRecord Model, 这里我们的定义fields方法就行,
    // AbstractDatabaseResource会自动接管, 帮我们序列化成JSON:API → 返回给前端
    public function fields(): array
    {
        return [
            Schema\Integer::make('rewardAmount')
                ->property('reward_amount'),
            Schema\DateTime::make('checkedInAt')
                ->property('checked_in_at'),

            Schema\Relationship\ToOne::make('user')
                ->type('users')
                ->includable(),
        ];
    }

    public function sorts(): array
    {
        return [];
    }
}
