<?php

namespace Donk\AigcCollectibles\Command;

use Donk\AigcCollectibles\Event\CollectibleMinted;
use Donk\AigcCollectibles\Model\Collectible;
use Donk\AigcCollectibles\Model\CollectibleEvent;
use Donk\AigcCollectibles\Model\Web3Account;
use Donk\AigcCollectibles\Repository\CollectibleRepository;
use Donk\AigcCollectibles\Service\Contracts\BlockchainServiceInterface;
use Donk\AigcCollectibles\Service\Contracts\IPFSServiceInterface;
use Flarum\Foundation\ValidationException;
use Illuminate\Contracts\Events\Dispatcher;
use RuntimeException;

class MintCollectibleHandler
{
    protected CollectibleRepository $collectibleRepository;
    protected BlockchainServiceInterface $blockchainService;
    protected IPFSServiceInterface $ipfsService;
    protected Dispatcher $events;

    public function __construct(
        CollectibleRepository $collectibleRepository,
        BlockchainServiceInterface $blockchainService,
        IPFSServiceInterface $ipfsService,
        Dispatcher $events
    ) {
        $this->collectibleRepository = $collectibleRepository;
        $this->blockchainService = $blockchainService;
        $this->ipfsService = $ipfsService;
        $this->events = $events;
    }

    public function handle(MintCollectible $command): Collectible
    {
        $actor = $command->actor;

        $actor->assertRegistered();

        $collectible = $this->collectibleRepository->findOrFail($command->collectibleId, $actor);

        $actor->assertCan('mint', $collectible);

        if (!$this->blockchainService->isMintingConfigured()) {
            throw new RuntimeException('NFT minting is not configured.');
        }

        $walletAccount = Web3Account::query()
            ->where('user_id', $actor->id)
            ->first();

        if (!$walletAccount) {
            throw new ValidationException([
                'wallet' => 'You must bind a wallet before minting.',
            ]);
        }

        if (empty($collectible->metadata_cid)) {
            throw new ValidationException([
                'metadata' => 'Collectible metadata is not available for minting.',
            ]);
        }

        $tokenURI = 'ipfs://' . $collectible->metadata_cid;

        $tokenId = $this->blockchainService->mintNFT($walletAccount->address, $tokenURI);

        $collectible->token_id = $tokenId;
        $collectible->save();

        $event = CollectibleEvent::log(
            $collectible,
            'minted',
            null,
            $actor->id,
            null,
            ['token_id' => $tokenId, 'address' => $walletAccount->address]
        );
        $event->save();

        $this->events->dispatch(new CollectibleMinted($actor, $collectible, $tokenId));

        return $collectible;
    }
}
