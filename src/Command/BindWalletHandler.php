<?php

namespace Donk\AigcCollectibles\Command;

use Donk\AigcCollectibles\Event\WalletBound;
use Donk\AigcCollectibles\Model\Web3Account;
use Donk\AigcCollectibles\Service\Contracts\BlockchainServiceInterface;
use Donk\AigcCollectibles\Validator\Web3LoginValidator;
use Flarum\Foundation\ValidationException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;

class BindWalletHandler
{
    protected BlockchainServiceInterface $blockchainService;

    protected Web3LoginValidator $validator;

    protected Dispatcher $events;

    protected CacheRepository $cache;

    public function __construct(
        BlockchainServiceInterface $blockchainService,
        Web3LoginValidator $validator,
        Dispatcher $events,
        CacheRepository $cache
    ) {
        $this->blockchainService = $blockchainService;
        $this->validator = $validator;
        $this->events = $events;
        $this->cache = $cache;
    }

    public function handle(BindWallet $command): Web3Account
    {
        $actor = $command->actor;
        $data = $command->data;

        $actor->assertRegistered();

        $attributes = Arr::get($data, 'data.attributes', []);
        $address = (string) Arr::get($attributes, 'address', '');
        $signature = (string) Arr::get($attributes, 'signature', '');
        $nonce = (string) Arr::get($attributes, 'nonce', '');

        $this->validator->assertValid([
            'address' => $address,
            'signature' => $signature,
            'nonce' => $nonce,
        ]);

        $normalizedAddress = strtolower($address);
        $domain = (string) ($data['domain'] ?? 'localhost');
        $nonceCacheKey = "donk-aigc-collectibles.web3-nonce.{$actor->id}.{$nonce}";
        $noncePayload = $this->cache->get($nonceCacheKey);

        if (! is_array($noncePayload)) {
            throw new ValidationException([
                'nonce' => 'Nonce is invalid or expired. Please request a new nonce.',
            ]);
        }

        $expectedDomain = (string) ($noncePayload['domain'] ?? '');
        $expectedAddress = strtolower((string) ($noncePayload['address'] ?? ''));
        $message = (string) ($noncePayload['message'] ?? '');

        if ($expectedDomain !== $domain || $expectedAddress !== $normalizedAddress || $message === '') {
            throw new ValidationException([
                'nonce' => 'Nonce does not match the requested wallet or domain.',
            ]);
        }

        // One-time nonce: consume before signature verification to prevent replay.
        $this->cache->forget($nonceCacheKey);

        $existingAccount = Web3Account::query()
            ->where('address', $normalizedAddress)
            ->first();

        if ($existingAccount) {
            throw new ValidationException([
                'address' => 'This wallet address is already bound to an account.',
            ]);
        }

        $existingUserAccount = Web3Account::query()
            ->where('user_id', $actor->id)
            ->first();

        if ($existingUserAccount) {
            throw new ValidationException([
                'wallet' => 'You already have a wallet bound. Unbind it first.',
            ]);
        }

        if (! $this->blockchainService->verifySignature($message, $signature, $normalizedAddress)) {
            throw new ValidationException([
                'signature' => 'Wallet signature verification failed.',
            ]);
        }

        $account = Web3Account::create($actor, $normalizedAddress);
        $account->save();

        $this->events->dispatch(new WalletBound($actor, $account));

        return $account;
    }
}
