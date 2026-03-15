<?php

namespace Donk\AigcCollectibles\Api\Serializer;

use Donk\AigcCollectibles\Model\Trade;
use Flarum\Api\Serializer\AbstractSerializer;
use Flarum\Api\Serializer\BasicUserSerializer;
use InvalidArgumentException;

class TradeSerializer extends AbstractSerializer
{
    protected $type = 'trades';

    protected function getDefaultAttributes($model): array
    {
        if (!($model instanceof Trade)) {
            throw new InvalidArgumentException(
                get_class($this) . ' can only serialize instances of ' . Trade::class
            );
        }

        return [
            'offeredBoxes' => (int) $model->offered_boxes,
            'status' => $model->status,
            'note' => $model->note,
            'completedAt' => $this->formatDate($model->completed_at),
            'createdAt' => $this->formatDate($model->created_at),
            'updatedAt' => $this->formatDate($model->updated_at),
            'canAccept' => $this->getActor()->can('accept', $model),
            'canReject' => $this->getActor()->can('reject', $model),
            'canCancel' => $this->getActor()->can('cancel', $model),
        ];
    }

    protected function fromUser($model)
    {
        return $this->hasOne($model, BasicUserSerializer::class);
    }

    protected function toUser($model)
    {
        return $this->hasOne($model, BasicUserSerializer::class);
    }

    protected function collectible($model)
    {
        return $this->hasOne($model, CollectibleSerializer::class);
    }
}
