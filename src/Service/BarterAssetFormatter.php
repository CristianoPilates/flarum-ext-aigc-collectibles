<?php

namespace Donk\AigcCollectibles\Service;

use Donk\AigcCollectibles\Model\BarterProposal;
use Donk\AigcCollectibles\Model\BlindBox;
use Donk\AigcCollectibles\Model\Collectible;

class BarterAssetFormatter
{
    /**
     * @return array<string, mixed>
     */
    public static function collectible(Collectible $collectible): array
    {
        return [
            'id' => $collectible->id,
            'kind' => BarterProposal::ASSET_COLLECTIBLE,
            'assetType' => BarterProposal::ASSET_COLLECTIBLE,
            'name' => $collectible->name,
            'rarity' => $collectible->rarity,
            'status' => $collectible->status,
            'tokenId' => $collectible->token_id,
            'ipfsCid' => $collectible->ipfs_cid,
            'metadataCid' => $collectible->metadata_cid,
            'ownerUserId' => $collectible->owner_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function blindBox(BlindBox $box): array
    {
        return [
            'id' => $box->id,
            'kind' => BarterProposal::ASSET_BLIND_BOX,
            'assetType' => BarterProposal::ASSET_BLIND_BOX,
            'type' => $box->type,
            'seed' => $box->seed,
            'status' => $box->status,
            'budget' => $box->budget,
            'ownerUserId' => $box->user_id,
        ];
    }
}
