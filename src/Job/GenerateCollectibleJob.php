<?php

namespace Donk\AigcCollectibles\Job;

use Donk\AigcCollectibles\Event\CollectibleGenerated;
use Donk\AigcCollectibles\Model\BlindBox;
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
use SM\Factory\FactoryInterface;

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
        Dispatcher $events,
        FactoryInterface $stateMachines
    ): void {
        $collectible = Collectible::query()->find($this->collectibleId);

        if (!$collectible) {
            return;
        }

        $stateMachine = $stateMachines->get($collectible, 'collectible');

        if ($collectible->status === Collectible::STATUS_DRAFT) {
            $stateMachine->apply('start_generating');
            $collectible->save();
        }

        if ($collectible->status !== Collectible::STATUS_GENERATING) {
            return;
        }

        $user = User::query()->find($collectible->owner_id);

        if (!$user) {
            $this->markFailed($collectible, $db, $stateMachines);
            return;
        }

        try {
            $prompt = $this->buildPrompt($collectible, $settings);

            $imageData = $aigcService->generateImage($prompt, $collectible->rarity);

            $imageCid = $ipfsService->upload($imageData);

            $metadata = [
                'name' => 'Collectible #' . $collectible->id,
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
            $stateMachine->apply('complete');
            $collectible->save();

            $events->dispatch(new CollectibleGenerated($user, $collectible));

        } catch (\Throwable $e) {
            if ($this->shouldFailPermanently()) {
                $this->markFailed($collectible, $db, $stateMachines);
            } else {
                throw $e;
            }
        }
    }

    protected function buildPrompt(Collectible $collectible, SettingsRepositoryInterface $settings): string
    {
        $basePrompt = $settings->get('donk-aigc-collectibles.aigc-base-prompt', 'A mystical digital collectible');
        $phrasePrompt = trim((string) $collectible->aigc_prompt);

        $rarityThemes = [
            'common' => 'a simple everyday object with subtle magical qualities',
            'rare' => 'a shimmering enchanted artifact with visible magical aura',
            'epic' => 'a powerful ancient relic radiating intense magical energy',
            'legendary' => 'a mythical divine artifact of immense cosmic power',
        ];

        $theme = $rarityThemes[$collectible->rarity] ?? $rarityThemes['common'];
        $segments = array_filter([
            trim((string) $basePrompt),
            $phrasePrompt,
            $theme,
        ]);

        return implode(', ', $segments);
    }

    protected function markFailed(Collectible $collectible, ConnectionInterface $db, FactoryInterface $stateMachines): void
    {
        $db->transaction(function () use ($collectible, $db, $stateMachines) {
            /** @var Collectible|null $lockedCollectible */
            $lockedCollectible = Collectible::query()
                ->lockForUpdate()
                ->find($collectible->id);

            if (!$lockedCollectible || $lockedCollectible->status !== Collectible::STATUS_GENERATING) {
                return;
            }

            $stateMachines->get($lockedCollectible, 'collectible')->apply('fail');
            $lockedCollectible->save();

            $user = User::query()->find($lockedCollectible->owner_id);
            if (!$user) {
                return;
            }

            $replacementBoxType = $db->table('blindboxes')
                ->where('collectible_id', $lockedCollectible->id)
                ->value('type') ?: 'checkin_reward';

            BlindBox::createForUser($user, (string) $replacementBoxType);

            $db->table('users')
                ->where('id', $lockedCollectible->owner_id)
                ->increment('blind_box_count', 1);
        });
    }

    protected function shouldFailPermanently(): bool
    {
        if ($this->attempts() >= $this->tries) {
            return true;
        }

        return $this->job?->getConnectionName() === 'sync';
    }
}
