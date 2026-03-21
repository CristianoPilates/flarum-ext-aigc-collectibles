<?php

namespace Donk\AigcCollectibles\Provider;

use Donk\AigcCollectibles\Service\AIGCService;
use Donk\AigcCollectibles\Service\BlindBoxService;
use Donk\AigcCollectibles\Service\BlockchainService;
use Donk\AigcCollectibles\Service\CheckinService;
use Donk\AigcCollectibles\Service\Contracts\AIGCServiceInterface;
use Donk\AigcCollectibles\Service\Contracts\BlindBoxServiceInterface;
use Donk\AigcCollectibles\Service\Contracts\BlockchainServiceInterface;
use Donk\AigcCollectibles\Service\Contracts\CheckinServiceInterface;
use Donk\AigcCollectibles\Service\Contracts\IPFSServiceInterface;
use Donk\AigcCollectibles\Service\Contracts\TradeServiceInterface;
use Donk\AigcCollectibles\Service\IPFSService;
use Donk\AigcCollectibles\Service\TradeService;
use Flarum\Foundation\AbstractServiceProvider;

class CollectibleServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(BlindBoxServiceInterface::class, BlindBoxService::class);
        $this->container->singleton(CheckinServiceInterface::class, CheckinService::class);
        $this->container->singleton(AIGCServiceInterface::class, AIGCService::class);
        $this->container->singleton(IPFSServiceInterface::class, IPFSService::class);
        $this->container->singleton(BlockchainServiceInterface::class, BlockchainService::class);
        $this->container->singleton(TradeServiceInterface::class, TradeService::class);
    }
}
