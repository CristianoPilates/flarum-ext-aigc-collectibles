<?php

namespace Donk\AigcCollectibles\Tests\Fake;

use Donk\AigcCollectibles\Service\Contracts\BlockchainServiceInterface;

/**
 * Fake blockchain service that returns predetermined values for testing.
 */
class FakeBlockchainService implements BlockchainServiceInterface
{
    public bool $mintingConfigured = true;
    public int $nextTokenId = 1001;
    public bool $signatureValid = true;
    public string $recoveredAddress = '';
    public int $mintCallCount = 0;
    public bool $shouldFailMint = false;

    public function verifySignature(string $message, string $signature, string $expectedAddress): bool
    {
        return $this->signatureValid;
    }

    public function recoverAddress(string $message, string $signature): string
    {
        return $this->recoveredAddress;
    }

    public function generateNonce(): string
    {
        return 'fake-nonce-' . bin2hex(random_bytes(8));
    }

    public function buildSignMessage(string $nonce, string $domain): string
    {
        return "Sign this message to verify your wallet on {$domain}.\n\nNonce: {$nonce}";
    }

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
