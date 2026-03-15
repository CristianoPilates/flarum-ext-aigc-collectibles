<?php

namespace Donk\AigcCollectibles\Api\Serializer;

use Donk\AigcCollectibles\Model\CollectibleEvent;
use Flarum\Api\Serializer\AbstractSerializer;
use Flarum\Api\Serializer\BasicUserSerializer;
use InvalidArgumentException;

class CollectibleEventSerializer extends AbstractSerializer
{
    protected $type = 'collectible-events';

    protected function getDefaultAttributes($model): array
    {
        if (!($model instanceof CollectibleEvent)) {
            throw new InvalidArgumentException(
                get_class($this) . ' can only serialize instances of ' . CollectibleEvent::class
            );
        }

        return [
            'eventType' => $model->event_type,
            'metadata' => $model->metadata,
            'createdAt' => $this->formatDate($model->created_at),
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
