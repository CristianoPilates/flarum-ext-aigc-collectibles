<?php

namespace Donk\AigcCollectibles\Provider;

use Donk\AigcCollectibles\Service\AIGCService;
use Donk\AigcCollectibles\Service\BlindBoxService;
use Donk\AigcCollectibles\Service\BlockchainService;
use Donk\AigcCollectibles\Service\CheckinService;
use Donk\AigcCollectibles\Service\IPFSService;
use Donk\AigcCollectibles\Service\TradeService;
use Flarum\Foundation\AbstractServiceProvider;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

class CollectibleServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(BlindBoxService::class, function (Container $container) {
            return new BlindBoxService(
                $container->make(ConnectionInterface::class)
            );
        });

        $this->container->singleton(CheckinService::class, function (Container $container) {
            return new CheckinService(
                $container->make(SettingsRepositoryInterface::class),
                $container->make(BlindBoxService::class),
                $container->make(ConnectionInterface::class),
                $container->make(Dispatcher::class)
            );
        });

        $this->container->singleton(AIGCService::class, function (Container $container) {
            return new AIGCService(
                $container->make(SettingsRepositoryInterface::class)
            );
        });

        $this->container->singleton(IPFSService::class, function (Container $container) {
            return new IPFSService(
                $container->make(SettingsRepositoryInterface::class)
            );
        });

        $this->container->singleton(BlockchainService::class, function (Container $container) {
            return new BlockchainService(
                $container->make(SettingsRepositoryInterface::class)
            );
        });

        $this->container->singleton(TradeService::class, function (Container $container) {
            return new TradeService(
                $container->make(BlindBoxService::class),
                $container->make(ConnectionInterface::class),
                $container->make(Dispatcher::class)
            );
        });
    }
}
