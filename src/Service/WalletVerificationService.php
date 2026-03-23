<?php

namespace Donk\AigcCollectibles\Service;

use Donk\AigcCollectibles\Model\Web3Account;
use Donk\AigcCollectibles\Service\Contracts\WalletVerificationServiceInterface;
use Elliptic\EC;
use Flarum\Foundation\ValidationException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use kornrunner\Keccak;

class WalletVerificationService implements WalletVerificationServiceInterface
{
    private const NONCE_CACHE_PREFIX = 'donk-aigc-collectibles.web3-nonce';
    private const NONCE_TTL_MINUTES = 5;

    public function __construct(
        protected CacheRepository $cache,
    ) {}

    public function generateNonce(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function buildSignMessage(string $nonce, string $domain): string
    {
        return "Sign this message to verify your wallet ownership.\n\n"
            . "Domain: {$domain}\n"
            . "Nonce: {$nonce}\n"
            . "Timestamp: " . time();
    }

    public function verifySignature(string $message, string $signature, string $expectedAddress): bool
    {
        $address = $this->recoverAddress($message, $signature);

        return strtolower($address) === strtolower($expectedAddress);
    }

    public function recoverAddress(string $message, string $signature): string
    {
        $personalMessage = "\x19Ethereum Signed Message:\n" . strlen($message) . $message;
        $msgHash = Keccak::hash($personalMessage, 256);

        $sign = [
            'r' => substr($signature, 2, 64),
            's' => substr($signature, 66, 64),
        ];

        $v = hexdec(substr($signature, 130, 2));

        if ($v < 27) {
            $v += 27;
        }

        $recId = $v - 27;

        $ec = new EC('secp256k1');
        $pubKey = $ec->recoverPubKey($msgHash, $sign, $recId);

        $pubKeyHex = $pubKey->encode('hex');
        $pubKeyWithoutPrefix = substr($pubKeyHex, 2);

        return '0x' . substr(Keccak::hash(hex2bin($pubKeyWithoutPrefix), 256), -40);
    }

    public function createNonceChallenge(int $userId, string $address, string $domain): array
    {
        $nonce = $this->generateNonce();
        $message = $this->buildSignMessage($nonce, $domain);

        $this->cache->put(
            $this->buildNonceCacheKey($userId, $nonce),
            [
                'address' => strtolower($address),
                'domain' => $domain,
                'message' => $message,
            ],
            new \DateTimeImmutable('+' . self::NONCE_TTL_MINUTES . ' minutes')
        );

        return [
            'nonce' => $nonce,
            'message' => $message,
        ];
    }

    public function validateAndConsumeNonce(int $userId, string $nonce, string $address, string $domain): string
    {
        $cacheKey = $this->buildNonceCacheKey($userId, $nonce);
        $payload = $this->cache->get($cacheKey);

        if (! is_array($payload)) {
            throw new ValidationException([
                'nonce' => 'Nonce is invalid or expired. Please request a new nonce.',
            ]);
        }

        // Consume immediately to prevent replay, regardless of validation outcome.
        $this->cache->forget($cacheKey);

        $expectedDomain = (string) ($payload['domain'] ?? '');
        $expectedAddress = strtolower((string) ($payload['address'] ?? ''));
        $message = (string) ($payload['message'] ?? '');

        if ($expectedDomain !== $domain || $expectedAddress !== strtolower($address) || $message === '') {
            throw new ValidationException([
                'nonce' => 'Nonce does not match the requested wallet or domain.',
            ]);
        }

        return $message;
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

    private function buildNonceCacheKey(int $userId, string $nonce): string
    {
        return self::NONCE_CACHE_PREFIX . ".{$userId}.{$nonce}";
    }
}
