<?php

namespace Donk\AigcCollectibles\Service\Contracts;

interface NftMintingServiceInterface
{
    /**
     * Mint an ERC-721 NFT on the configured blockchain.
     *
     * @return int The minted token ID.
     * @throws \RuntimeException If minting is not configured or the transaction fails.
     */
    public function mintNFT(string $toAddress, string $tokenURI): int;

    public function isMintingConfigured(): bool;
}
