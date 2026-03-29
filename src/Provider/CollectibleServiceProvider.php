<?php

namespace Donk\AigcCollectibles\Provider;

use Donk\AigcCollectibles\Api\UserResourceFields;
use Donk\AigcCollectibles\Service\AIGCService;
use Donk\AigcCollectibles\Service\BlindBoxService;
use Donk\AigcCollectibles\Service\CheckinService;
use Donk\AigcCollectibles\Service\CollectibleProofService;
use Donk\AigcCollectibles\Service\Contracts\AIGCServiceInterface;
use Donk\AigcCollectibles\Service\Contracts\BlindBoxServiceInterface;
use Donk\AigcCollectibles\Service\Contracts\CheckinServiceInterface;
use Donk\AigcCollectibles\Service\Contracts\CollectibleProofServiceInterface;
use Donk\AigcCollectibles\Service\Contracts\IPFSServiceInterface;
use Donk\AigcCollectibles\Service\Contracts\NftMintingServiceInterface;
use Donk\AigcCollectibles\Service\Contracts\TradeServiceInterface;
use Donk\AigcCollectibles\Service\Contracts\WalletVerificationServiceInterface;
use Donk\AigcCollectibles\Service\IPFSService;
use Donk\AigcCollectibles\Service\NftMintingService;
use Donk\AigcCollectibles\Service\TradeService;
use Donk\AigcCollectibles\Service\WalletVerificationService;
use Donk\AigcCollectibles\StateMachine\StateMachineConfig;
use Flarum\Api\Resource\UserResource;
use Flarum\Foundation\AbstractServiceProvider;
use SM\Factory\Factory;
use SM\Factory\FactoryInterface;

class CollectibleServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(FactoryInterface::class, static function () {
            return new Factory([
                StateMachineConfig::collectible(),
                StateMachineConfig::blindBox(),
                StateMachineConfig::trade(),
            ]);
        });

        $this->container->singleton(BlindBoxServiceInterface::class, BlindBoxService::class);
        $this->container->singleton(CheckinServiceInterface::class, CheckinService::class);
        $this->container->singleton(CollectibleProofServiceInterface::class, CollectibleProofService::class);
        $this->container->singleton(AIGCServiceInterface::class, AIGCService::class);
        $this->container->singleton(IPFSServiceInterface::class, IPFSService::class);
        $this->container->singleton(WalletVerificationServiceInterface::class, WalletVerificationService::class);
        $this->container->singleton(NftMintingServiceInterface::class, NftMintingService::class);
        $this->container->singleton(TradeServiceInterface::class, TradeService::class);
    }

    public function boot(): void
    {
        UserResource::mutateFields(function (array $fields) {
            $userResourceFields = $this->container->make(UserResourceFields::class);

            return array_merge($fields, $userResourceFields());
        });
    }
}
