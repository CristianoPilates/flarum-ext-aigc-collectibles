<?php

namespace Donk\AigcCollectibles\Service\Contracts;

interface WalletVerificationServiceInterface
{
    public function generateNonce(): string;

    public function buildSignMessage(string $nonce, string $domain): string;

    public function verifySignature(string $message, string $signature, string $expectedAddress): bool;

    public function recoverAddress(string $message, string $signature): string;

    /**
     * Generate a nonce challenge and cache it for later verification.
     *
     * @return array{nonce: string, message: string}
     */
    public function createNonceChallenge(int $userId, string $address, string $domain): array;

    /**
     * Validate the nonce against the cached challenge and consume it (one-time use).
     *
     * @return string The original sign message associated with this nonce.
     * @throws \Flarum\Foundation\ValidationException If nonce is invalid, expired, or context mismatched.
     */
    public function validateAndConsumeNonce(int $userId, string $nonce, string $address, string $domain): string;

    /**
     * @throws \Flarum\Foundation\ValidationException If address is already bound.
     */
    public function ensureAddressAvailable(string $address): void;

    /**
     * @throws \Flarum\Foundation\ValidationException If user already has a wallet.
     */
    public function ensureUserHasNoWallet(int $userId): void;
}
