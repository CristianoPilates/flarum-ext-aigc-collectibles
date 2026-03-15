<?php

namespace Donk\AigcCollectibles\Api\Serializer;

use Donk\AigcCollectibles\Model\CheckinRecord;
use Flarum\Api\Serializer\AbstractSerializer;
use Flarum\Api\Serializer\BasicUserSerializer;
use InvalidArgumentException;

class CheckinRecordSerializer extends AbstractSerializer
{
    protected $type = 'checkin-records';

    protected function getDefaultAttributes($model): array
    {
        if (!($model instanceof CheckinRecord)) {
            throw new InvalidArgumentException(
                get_class($this) . ' can only serialize instances of ' . CheckinRecord::class
            );
        }

        return [
            'rewardAmount' => (int) $model->reward_amount,
            'checkedInAt' => $this->formatDate($model->checked_in_at),
        ];
    }

    protected function user($model)
    {
        return $this->hasOne($model, BasicUserSerializer::class);
    }
}
