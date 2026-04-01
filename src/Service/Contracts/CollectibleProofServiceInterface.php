<?php

namespace Donk\AigcCollectibles\Service\Contracts;

use Donk\AigcCollectibles\Model\Collectible;

interface CollectibleProofServiceInterface
{
    /**
     * @return array<string, mixed>
     */
    public function buildProof(Collectible $collectible): array;
}
