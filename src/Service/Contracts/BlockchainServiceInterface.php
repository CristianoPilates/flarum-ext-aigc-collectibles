<?php

namespace Donk\AigcCollectibles\Service\Contracts;

interface BlockchainServiceInterface
{
    public function verifySignature(string $message, string $signature, string $expectedAddress): bool;

    public function recoverAddress(string $message, string $signature): string;

    public function generateNonce(): string;

    public function buildSignMessage(string $nonce, string $domain): string;

    public function mintNFT(string $toAddress, string $tokenURI): int;

    public function isMintingConfigured(): bool;
}
