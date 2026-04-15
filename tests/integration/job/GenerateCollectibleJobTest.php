<?php

namespace Donk\AigcCollectibles\Tests\integration\job;

use Donk\AigcCollectibles\Job\GenerateCollectibleJob;
use Donk\AigcCollectibles\Service\Contracts\AIGCServiceInterface;
use Donk\AigcCollectibles\Service\Contracts\BlindBoxServiceInterface;
use Donk\AigcCollectibles\Service\Contracts\IPFSServiceInterface;
use Donk\AigcCollectibles\Tests\Fake\FakeAIGCService;
use Donk\AigcCollectibles\Tests\Fake\FakeIPFSService;
use Flarum\Extend;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Queue\Jobs\SyncJob;
use PHPUnit\Framework\Attributes\Test;
use SM\Factory\FactoryInterface;

class GenerateCollectibleJobTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('donk-aigc-collectibles');

        $this->extend(
            (new Extend\ServiceProvider())
                ->register(GenerateCollectibleJobTestServiceOverrides::class)
        );

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
            ],
            'collectibles' => [
                [
                    'id' => 1,
                    'owner_id' => 2,
                    'rarity' => 'rare',
                    'status' => 'generating',
                    'ipfs_cid' => null,
                    'metadata_cid' => null,
                    'aigc_prompt' => 'test prompt',
                    'token_id' => null,
                    'times_traded' => 0,
                    'created_at' => '2026-01-01 00:00:00',
                    'updated_at' => '2026-01-01 00:00:00',
                ],
            ],
            'blindboxes' => [
                [
                    'id' => 10,
                    'user_id' => 2,
                    'type' => 'checkin_reward',
                    'seed' => str_repeat('a', 64),
                    'status' => 'opened',
                    'budget' => 40,
                    'collectible_id' => 1,
                    'created_at' => '2026-01-01 00:00:00',
                    'updated_at' => '2026-01-01 00:00:00',
                ],
            ],
        ]);
    }

    #[Test]
    public function successful_generation_completes_collectible_without_auto_minting(): void
    {
        $container = $this->app()->getContainer();

        $job = new GenerateCollectibleJob(1);

        $job->handle(
            $container->make(AIGCServiceInterface::class),
            $container->make(IPFSServiceInterface::class),
            $container->make(SettingsRepositoryInterface::class),
            $container->make(ConnectionInterface::class),
            $container->make(Dispatcher::class),
            $container->make(FactoryInterface::class),
            $container->make(BlindBoxServiceInterface::class),
        );

        $collectible = $this->database()->table('collectibles')->where('id', 1)->first();
        $this->assertSame('completed', $collectible->status);
        $this->assertSame('QmFakeImageCid1', $collectible->ipfs_cid);
        $this->assertSame('QmFakeMetadataCid1', $collectible->metadata_cid);
        $this->assertNull($collectible->token_id);
    }

    #[Test]
    public function failed_generation_marks_collectible_failed_and_restores_a_real_blind_box(): void
    {
        $container = $this->app()->getContainer();

        /** @var FakeAIGCService $aigc */
        $aigc = $container->make(AIGCServiceInterface::class);
        $aigc->shouldFail = true;

        $job = new GenerateCollectibleJob(1);
        $job->tries = 1;

        $job->handle(
            $aigc,
            $container->make(IPFSServiceInterface::class),
            $container->make(SettingsRepositoryInterface::class),
            $container->make(ConnectionInterface::class),
            $container->make(Dispatcher::class),
            $container->make(FactoryInterface::class),
            $container->make(BlindBoxServiceInterface::class),
        );

        $collectible = $this->database()->table('collectibles')->where('id', 1)->first();
        $this->assertSame('failed', $collectible->status);

        $user = $this->database()->table('users')->where('id', 2)->first();
        $this->assertSame(1, (int) $user->blind_box_count);

        $replacementBoxes = $this->database()->table('blindboxes')
            ->where('user_id', 2)
            ->where('status', 'unappraised')
            ->get();

        $this->assertCount(1, $replacementBoxes);
        $this->assertSame('checkin_reward', $replacementBoxes[0]->type);
        $this->assertNull($replacementBoxes[0]->collectible_id);

        $originalBox = $this->database()->table('blindboxes')->where('id', 10)->first();
        $this->assertSame('opened', $originalBox->status);
        $this->assertSame(1, (int) $originalBox->collectible_id);
    }

    #[Test]
    public function sync_queue_failures_are_treated_as_final_attempts_and_refunded(): void
    {
        $container = $this->app()->getContainer();

        /** @var FakeAIGCService $aigc */
        $aigc = $container->make(AIGCServiceInterface::class);
        $aigc->shouldFail = true;

        $job = (new GenerateCollectibleJob(1))
            ->setJob(new SyncJob($container, json_encode([]), 'sync', 'sync'));

        $job->handle(
            $aigc,
            $container->make(IPFSServiceInterface::class),
            $container->make(SettingsRepositoryInterface::class),
            $container->make(ConnectionInterface::class),
            $container->make(Dispatcher::class),
            $container->make(FactoryInterface::class),
            $container->make(BlindBoxServiceInterface::class),
        );

        $collectible = $this->database()->table('collectibles')->where('id', 1)->first();
        $this->assertSame('failed', $collectible->status);

        $user = $this->database()->table('users')->where('id', 2)->first();
        $this->assertSame(1, (int) $user->blind_box_count);

        $replacementBoxes = $this->database()->table('blindboxes')
            ->where('user_id', 2)
            ->where('status', 'unappraised')
            ->get();

        $this->assertCount(1, $replacementBoxes);
        $this->assertSame('checkin_reward', $replacementBoxes[0]->type);
    }
}

class GenerateCollectibleJobTestServiceOverrides extends \Flarum\Foundation\AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(AIGCServiceInterface::class, FakeAIGCService::class);
        $this->container->singleton(IPFSServiceInterface::class, FakeIPFSService::class);
    }
}
