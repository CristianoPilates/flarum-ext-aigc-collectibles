<?php

namespace Donk\AigcCollectibles\Api\Serializer;

use Donk\AigcCollectibles\Model\Collectible;
use Flarum\Api\Serializer\AbstractSerializer;
use Flarum\Api\Serializer\BasicUserSerializer;
use InvalidArgumentException;

class CollectibleSerializer extends AbstractSerializer
{
    protected $type = 'collectibles';

    protected function getDefaultAttributes($model): array
    {
        if (!($model instanceof Collectible)) {
            throw new InvalidArgumentException(
                get_class($this) . ' can only serialize instances of ' . Collectible::class
            );
        }

        $attributes = [
            'name' => $model->name,
            'rarity' => $model->rarity,
            'status' => $model->status,
            'ipfsCid' => $model->ipfs_cid,
            'metadataCid' => $model->metadata_cid,
            'tokenId' => $model->token_id,
            'timesTraded' => (int) $model->times_traded,
            'createdAt' => $this->formatDate($model->created_at),
            'updatedAt' => $this->formatDate($model->updated_at),
        ];

        if ($this->getActor()->id === $model->user_id) {
            $attributes['aigcPrompt'] = $model->aigc_prompt;
            $attributes['canTrade'] = true;
            $attributes['canMint'] = $model->token_id === null && $model->status === 'completed';
        }

        return $attributes;
    }

    protected function user($model)
    {
        return $this->hasOne($model, BasicUserSerializer::class);
    }

    protected function originalUser($model)
    {
        return $this->hasOne($model, BasicUserSerializer::class);
    }

    protected function events($model)
    {
        return $this->hasMany($model, CollectibleEventSerializer::class);
    }

    protected function trades($model)
    {
        return $this->hasMany($model, TradeSerializer::class);
    }
}
