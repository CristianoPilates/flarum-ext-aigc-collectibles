<?php

namespace Donk\AigcCollectibles\Job;

use Donk\AigcCollectibles\Event\CollectibleGenerated;
use Donk\AigcCollectibles\Model\Collectible;
use Donk\AigcCollectibles\Service\Contracts\AIGCServiceInterface;
use Donk\AigcCollectibles\Service\Contracts\BlindBoxServiceInterface;
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
        SettingsRepositoryInterface $settings,
        ConnectionInterface $db,
        Dispatcher $events,
        FactoryInterface $stateMachines,
        BlindBoxServiceInterface $blindBoxService
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
            $this->markFailed($collectible, $db, $stateMachines, $blindBoxService);
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

            $collectible->ipfs_cid = $imageCid;
            $collectible->metadata_cid = $metadataCid;
            $collectible->aigc_prompt = $prompt;
            $collectible->token_id = null;
            $stateMachine->apply('complete');
            $collectible->save();

            $events->dispatch(new CollectibleGenerated($user, $collectible));

        } catch (\Throwable $e) {
            if ($this->shouldFailPermanently()) {
                $this->markFailed($collectible, $db, $stateMachines, $blindBoxService);
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

    protected function markFailed(
        Collectible $collectible,
        ConnectionInterface $db,
        FactoryInterface $stateMachines,
        BlindBoxServiceInterface $blindBoxService
    ): void
    {
        $db->transaction(function () use ($collectible, $db, $stateMachines, $blindBoxService) {
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

            $blindBoxService->createForUser($user, (string) $replacementBoxType);
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
