<?php

namespace Donk\AigcCollectibles\Command;

use Donk\AigcCollectibles\Event\BlindBoxOpened;
use Donk\AigcCollectibles\Job\GenerateCollectibleJob;
use Donk\AigcCollectibles\Model\Collectible;
use Donk\AigcCollectibles\Model\CollectibleEvent;
use Donk\AigcCollectibles\Service\Contracts\BlindBoxServiceInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Database\ConnectionInterface;

class OpenBlindBoxHandler
{
    protected BlindBoxServiceInterface $blindBoxService;
    protected SettingsRepositoryInterface $settings;
    protected ConnectionInterface $db;
    protected Queue $queue;
    protected Dispatcher $events;

    public function __construct(
        BlindBoxServiceInterface $blindBoxService,
        SettingsRepositoryInterface $settings,
        ConnectionInterface $db,
        Queue $queue,
        Dispatcher $events
    ) {
        $this->blindBoxService = $blindBoxService;
        $this->settings = $settings;
        $this->db = $db;
        $this->queue = $queue;
        $this->events = $events;
    }

    public function handle(OpenBlindBox $command): Collectible
    {
        $actor = $command->actor;

        $actor->assertRegistered();

        $rarity = $this->rollRarity();

        $collectible = $this->db->transaction(function () use ($actor, $rarity) {
            $this->blindBoxService->spend($actor, 1);

            $name = $this->generateName($rarity);
            $collectible = Collectible::createForUser($actor, $rarity, $name);
            $collectible->save();

            $event = CollectibleEvent::log(
                $collectible,
                'generated',
                null,
                $actor->id
            );
            $event->save();

            return $collectible;
        });

        $this->queue->push(new GenerateCollectibleJob($collectible->id));

        $this->events->dispatch(new BlindBoxOpened($actor, $collectible, $rarity));

        return $collectible;
    }

    protected function rollRarity(): string
    {
        $common = (int) $this->settings->get('donk-aigc-collectibles.rarity-common', 60);
        $rare = (int) $this->settings->get('donk-aigc-collectibles.rarity-rare', 25);
        $epic = (int) $this->settings->get('donk-aigc-collectibles.rarity-epic', 12);
        $legendary = (int) $this->settings->get('donk-aigc-collectibles.rarity-legendary', 3);

        $total = $common + $rare + $epic + $legendary;
        $roll = random_int(1, $total);

        if ($roll <= $common) {
            return 'common';
        }

        if ($roll <= $common + $rare) {
            return 'rare';
        }

        if ($roll <= $common + $rare + $epic) {
            return 'epic';
        }

        return 'legendary';
    }

    protected function generateName(string $rarity): string
    {
        $prefixes = [
            'common' => ['Simple', 'Basic', 'Plain', 'Modest', 'Standard'],
            'rare' => ['Shining', 'Gleaming', 'Radiant', 'Luminous', 'Polished'],
            'epic' => ['Majestic', 'Grand', 'Exalted', 'Magnificent', 'Splendid'],
            'legendary' => ['Mythic', 'Eternal', 'Divine', 'Celestial', 'Primordial'],
        ];

        $nouns = ['Crystal', 'Prism', 'Artifact', 'Relic', 'Essence', 'Fragment', 'Token', 'Gem', 'Shard', 'Orb'];

        $prefix = $prefixes[$rarity][array_rand($prefixes[$rarity])];
        $noun = $nouns[array_rand($nouns)];

        return $prefix . ' ' . $noun;
    }
}
