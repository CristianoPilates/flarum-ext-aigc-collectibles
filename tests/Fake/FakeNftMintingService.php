<?php

namespace Donk\AigcCollectibles\Tests\Fake;

use Donk\AigcCollectibles\Service\Contracts\NftMintingServiceInterface;

class FakeNftMintingService implements NftMintingServiceInterface
{
    public bool $mintingConfigured = true;
    public int $nextTokenId = 1001;
    public int $mintCallCount = 0;
    public bool $shouldFailMint = false;

    public function mintNFT(string $toAddress, string $tokenURI): int
    {
        $this->mintCallCount++;

        if ($this->shouldFailMint) {
            throw new \RuntimeException('Fake minting failed.');
        }

        return $this->nextTokenId++;
    }

    public function isMintingConfigured(): bool
    {
        return $this->mintingConfigured;
    }
}
