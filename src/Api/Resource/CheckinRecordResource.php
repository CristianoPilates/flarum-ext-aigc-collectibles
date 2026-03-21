<?php

namespace Donk\AigcCollectibles\Api\Resource;

use Donk\AigcCollectibles\Command\Checkin;
use Donk\AigcCollectibles\Model\CheckinRecord;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Bus\Dispatcher;

/**
 * @extends AbstractDatabaseResource<CheckinRecord>
 */
class CheckinRecordResource extends AbstractDatabaseResource
{
    public function __construct(
        protected Dispatcher $bus,
    ) {
    }

    public function type(): string
    {
        return 'checkin-records';
    }

    public function model(): string
    {
        return CheckinRecord::class;
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Endpoint::make('checkin')
                ->route('POST', '/')
                ->authenticated()
                ->action(function (Context $context) {
                    return $this->bus->dispatch(
                        new Checkin($context->getActor())
                    );
                }),
        ];
    }

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
