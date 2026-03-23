<?php

namespace Donk\AigcCollectibles\Command;

use Donk\AigcCollectibles\Event\WalletUnbound;
use Donk\AigcCollectibles\Model\Web3Account;
use Flarum\Foundation\ValidationException;
use Illuminate\Contracts\Events\Dispatcher;

class UnbindWalletHandler
{
    public function __construct(
        protected readonly Dispatcher $events,
    ) {}

    public function handle(UnbindWallet $command): void
    {
        $actor = $command->actor;
        $actor->assertRegistered();

        $account = Web3Account::query()
            ->where('id', $command->accountId)
            ->firstOrFail();

        if ($account->user_id !== $actor->id) {
            throw new ValidationException([
                'account' => 'You can only unbind your own wallet.',
            ]);
        }

        $account->delete();

        $this->events->dispatch(new WalletUnbound($actor, $account));
    }
}
