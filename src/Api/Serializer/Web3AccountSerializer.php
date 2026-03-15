<?php

namespace Donk\AigcCollectibles\Api\Serializer;

use Donk\AigcCollectibles\Model\Web3Account;
use Flarum\Api\Serializer\AbstractSerializer;
use Flarum\Api\Serializer\BasicUserSerializer;
use InvalidArgumentException;

class Web3AccountSerializer extends AbstractSerializer
{
    protected $type = 'web3-accounts';

    protected function getDefaultAttributes($model): array
    {
        if (!($model instanceof Web3Account)) {
            throw new InvalidArgumentException(
                get_class($this) . ' can only serialize instances of ' . Web3Account::class
            );
        }

        return [
            'address' => $model->address,
            'source' => $model->source,
            'type' => $model->type,
            'attachedAt' => $this->formatDate($model->attached_at),
            'lastVerifiedAt' => $this->formatDate($model->last_verified_at),
        ];
    }

    protected function user($model)
    {
        return $this->hasOne($model, BasicUserSerializer::class);
    }
}
