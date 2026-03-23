<?php

namespace Donk\AigcCollectibles\Command;

use Donk\AigcCollectibles\Event\WalletBound;
use Donk\AigcCollectibles\Model\Web3Account;
use Donk\AigcCollectibles\Service\Contracts\WalletVerificationServiceInterface;
use Donk\AigcCollectibles\Validator\Web3LoginValidator;
use Flarum\Foundation\ValidationException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;

class BindWalletHandler
{
    public function __construct(
        protected readonly WalletVerificationServiceInterface $walletService,
        protected readonly Web3LoginValidator $validator,
        protected readonly Dispatcher $events,
    ) {}

    public function handle(BindWallet $command): Web3Account
    {
        $actor = $command->actor;
        $actor->assertRegistered();

        $attributes = Arr::get($command->data, 'data.attributes', []);
        $address = (string) Arr::get($attributes, 'address', '');
        $signature = (string) Arr::get($attributes, 'signature', '');
        $nonce = (string) Arr::get($attributes, 'nonce', '');
        $domain = (string) ($command->data['domain'] ?? 'localhost');

        $this->validator->assertValid([
            'address' => $address,
            'signature' => $signature,
            'nonce' => $nonce,
        ]);

        $normalizedAddress = strtolower($address);

        $message = $this->walletService->validateAndConsumeNonce(
            $actor->id, $nonce, $normalizedAddress, $domain
        );

        $this->walletService->ensureAddressAvailable($normalizedAddress);
        $this->walletService->ensureUserHasNoWallet($actor->id);

        if (! $this->walletService->verifySignature($message, $signature, $normalizedAddress)) {
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
