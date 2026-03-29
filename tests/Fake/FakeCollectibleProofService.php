<?php

namespace Donk\AigcCollectibles\Tests\Fake;

use Donk\AigcCollectibles\Model\Collectible;
use Donk\AigcCollectibles\Service\Contracts\CollectibleProofServiceInterface;

class FakeCollectibleProofService implements CollectibleProofServiceInterface
{
    public ?int $lastCollectibleId = null;

    public function buildProof(Collectible $collectible): array
    {
        $this->lastCollectibleId = $collectible->id;

        return [
            'app' => [
                'collectibleId' => $collectible->id,
                'tokenId' => $collectible->token_id,
                'metadataCid' => $collectible->metadata_cid,
                'ipfsCid' => $collectible->ipfs_cid,
            ],
            'chain' => [
                'available' => $collectible->token_id !== null,
                'state' => $collectible->token_id !== null ? 'verified' : 'not_minted',
                'ownerOf' => $collectible->token_id !== null ? '0xabb6bc9ec4c33cf50b4013ff8bb3b4855e35168f' : null,
                'tokenURI' => $collectible->metadata_cid ? 'ipfs://'.$collectible->metadata_cid : null,
            ],
            'metadata' => [
                'available' => $collectible->metadata_cid !== null,
                'cid' => $collectible->metadata_cid,
            ],
            'image' => [
                'available' => $collectible->ipfs_cid !== null,
                'cid' => $collectible->ipfs_cid,
            ],
        ];
    }
}
