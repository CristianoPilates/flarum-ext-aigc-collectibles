<?php

namespace Donk\AigcCollectibles\Job;

use Donk\AigcCollectibles\Event\CollectibleGenerated;
use Donk\AigcCollectibles\Model\Collectible;
use Donk\AigcCollectibles\Model\Web3Account;
use Donk\AigcCollectibles\Service\Contracts\AIGCServiceInterface;
use Donk\AigcCollectibles\Service\Contracts\NftMintingServiceInterface;
use Donk\AigcCollectibles\Service\Contracts\IPFSServiceInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateCollectibleJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    protected int $collectibleId;

    public function __construct(int $collectibleId)
    {
        $this->collectibleId = $collectibleId;
    }

    public function handle(
        AIGCServiceInterface $aigcService,
        IPFSServiceInterface $ipfsService,
        NftMintingServiceInterface $nftMintingService,
        SettingsRepositoryInterface $settings,
        ConnectionInterface $db,
        Dispatcher $events
    ): void {
        $collectible = Collectible::query()->find($this->collectibleId);

        if (!$collectible || $collectible->status !== 'generating') {
            return;
        }

        $user = User::query()->find($collectible->user_id);

        if (!$user) {
            $this->markFailed($collectible, $db);
            return;
        }

        try {
            $prompt = $this->buildPrompt($collectible, $settings);

            $imageData = $aigcService->generateImage($prompt, $collectible->rarity);

            $imageCid = $ipfsService->upload($imageData);

            $metadata = [
                'name' => $collectible->name,
                'image' => 'ipfs://' . $imageCid,
                'attributes' => [
                    ['trait_type' => 'rarity', 'value' => ucfirst($collectible->rarity)],
                    ['trait_type' => 'generation', 'value' => 'aigc'],
                ],
            ];

            $metadataCid = $ipfsService->uploadJson($metadata);

            $tokenId = null;

            if ($nftMintingService->isMintingConfigured()) {
                $walletAccount = Web3Account::query()
                    ->where('user_id', $user->id)
                    ->first();

                if ($walletAccount) {
                    try {
                        $tokenURI = 'ipfs://' . $metadataCid;
                        $tokenId = $nftMintingService->mintNFT($walletAccount->address, $tokenURI);
                    } catch (\Throwable $e) {
                        // Minting failure is non-critical; user can mint later
                    }
                }
            }

            $collectible->ipfs_cid = $imageCid;
            $collectible->metadata_cid = $metadataCid;
            $collectible->aigc_prompt = $prompt;
            $collectible->token_id = $tokenId;
            $collectible->status = 'completed';
            $collectible->save();

            $events->dispatch(new CollectibleGenerated($user, $collectible));

        } catch (\Throwable $e) {
            if ($this->attempts() >= $this->tries) {
                $this->markFailed($collectible, $db);
            } else {
                throw $e;
            }
        }
    }

    protected function buildPrompt(Collectible $collectible, SettingsRepositoryInterface $settings): string
    {
        $basePrompt = $settings->get('donk-aigc-collectibles.aigc-base-prompt', 'A mystical digital collectible');

        $rarityThemes = [
            'common' => 'a simple everyday object with subtle magical qualities',
            'rare' => 'a shimmering enchanted artifact with visible magical aura',
            'epic' => 'a powerful ancient relic radiating intense magical energy',
            'legendary' => 'a mythical divine artifact of immense cosmic power',
        ];

        $theme = $rarityThemes[$collectible->rarity] ?? $rarityThemes['common'];

        return $basePrompt . ', ' . $theme . ', named "' . $collectible->name . '"';
    }

    protected function markFailed(Collectible $collectible, ConnectionInterface $db): void
    {
        $db->transaction(function () use ($collectible, $db) {
            $collectible->status = 'failed';
            $collectible->save();

            // Refund the blind box
            $db->table('users')
                ->where('id', $collectible->user_id)
                ->increment('blind_box_count', 1);
        });
    }
}
