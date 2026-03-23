<?php

namespace Donk\AigcCollectibles\Tests\Fake;

use Donk\AigcCollectibles\Model\Web3Account;
use Donk\AigcCollectibles\Service\Contracts\WalletVerificationServiceInterface;
use Flarum\Foundation\ValidationException;

/**
 * Fake that replaces ECDSA signature verification with configurable flags,
 * while keeping real DB checks and in-memory nonce lifecycle for integration tests.
 */
class FakeWalletVerificationService implements WalletVerificationServiceInterface
{
    public bool $signatureValid = true;
    public string $recoveredAddress = '';

    /** @var array<string, array{address: string, domain: string, message: string}> keyed by "userId.nonce" */
    private array $nonceChallenges = [];

    public function generateNonce(): string
    {
        return 'fake-nonce-' . bin2hex(random_bytes(8));
    }

    public function buildSignMessage(string $nonce, string $domain): string
    {
        return "Sign this message to verify your wallet on {$domain}.\n\nNonce: {$nonce}";
    }

    public function verifySignature(string $message, string $signature, string $expectedAddress): bool
    {
        return $this->signatureValid;
    }

    public function recoverAddress(string $message, string $signature): string
    {
        return $this->recoveredAddress;
    }

    public function createNonceChallenge(int $userId, string $address, string $domain): array
    {
        $nonce = $this->generateNonce();
        $message = $this->buildSignMessage($nonce, $domain);

        $this->nonceChallenges["{$userId}.{$nonce}"] = [
            'address' => strtolower($address),
            'domain' => $domain,
            'message' => $message,
        ];

        return [
            'nonce' => $nonce,
            'message' => $message,
        ];
    }

    public function validateAndConsumeNonce(int $userId, string $nonce, string $address, string $domain): string
    {
        $key = "{$userId}.{$nonce}";
        $payload = $this->nonceChallenges[$key] ?? null;

        if ($payload === null) {
            throw new ValidationException([
                'nonce' => 'Nonce is invalid or expired. Please request a new nonce.',
            ]);
        }

        unset($this->nonceChallenges[$key]);

        if ($payload['domain'] !== $domain || $payload['address'] !== strtolower($address)) {
            throw new ValidationException([
                'nonce' => 'Nonce does not match the requested wallet or domain.',
            ]);
        }

        return $payload['message'];
    }

    public function ensureAddressAvailable(string $address): void
    {
        if (Web3Account::query()->where('address', strtolower($address))->exists()) {
            throw new ValidationException([
                'address' => 'This wallet address is already bound to an account.',
            ]);
        }
    }

    public function ensureUserHasNoWallet(int $userId): void
    {
        if (Web3Account::query()->where('user_id', $userId)->exists()) {
            throw new ValidationException([
                'wallet' => 'You already have a wallet bound. Unbind it first.',
            ]);
        }
    }
}
