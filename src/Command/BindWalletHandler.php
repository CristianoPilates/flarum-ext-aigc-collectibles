<?php

namespace Donk\AigcCollectibles\Command;

use Donk\AigcCollectibles\Event\WalletBound;
use Donk\AigcCollectibles\Model\Web3Account;
use Donk\AigcCollectibles\Service\Contracts\BlockchainServiceInterface;
use Donk\AigcCollectibles\Validator\Web3LoginValidator;
use Flarum\Foundation\ValidationException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;

class BindWalletHandler
{
    protected BlockchainServiceInterface $blockchainService;
    protected Web3LoginValidator $validator;
    protected Dispatcher $events;

    public function __construct(
        BlockchainServiceInterface $blockchainService,
        Web3LoginValidator $validator,
        Dispatcher $events
    ) {
        $this->blockchainService = $blockchainService;
        $this->validator = $validator;
        $this->events = $events;
    }

    public function handle(BindWallet $command): Web3Account
    {
        $actor = $command->actor;
        $data = $command->data;

        $actor->assertRegistered();

        $address = Arr::get($data, 'data.attributes.address');
        $signature = Arr::get($data, 'data.attributes.signature');
        $nonce = Arr::get($data, 'data.attributes.nonce');

        $this->validator->assertValid([
            'address' => $address,
            'signature' => $signature,
            'nonce' => $nonce,
        ]);

        $existingAccount = Web3Account::query()
            ->where('address', strtolower($address))
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

        $message = $this->blockchainService->buildSignMessage($nonce, $data['domain'] ?? 'localhost');

        if (!$this->blockchainService->verifySignature($message, $signature, $address)) {
            throw new ValidationException([
                'signature' => 'Wallet signature verification failed.',
            ]);
        }

        $account = Web3Account::create($actor, $address);
        $account->save();

        $this->events->dispatch(new WalletBound($actor, $account));

        return $account;
    }
}
