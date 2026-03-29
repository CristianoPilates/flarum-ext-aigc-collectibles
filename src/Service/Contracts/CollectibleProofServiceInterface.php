<?php

namespace Donk\AigcCollectibles\Service\Contracts;

use Donk\AigcCollectibles\Model\Collectible;

interface CollectibleProofServiceInterface
{
    public function buildProof(Collectible $collectible): array;
}
