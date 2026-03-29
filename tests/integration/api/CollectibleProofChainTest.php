<?php

namespace Donk\AigcCollectibles\Tests\integration\api;

use Donk\AigcCollectibles\Service\Contracts\CollectibleProofServiceInterface;
use Donk\AigcCollectibles\Tests\Fake\FakeCollectibleProofService;
use Flarum\Extend;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;

class CollectibleProofChainTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('donk-aigc-collectibles');

        $this->extend(
            (new Extend\ServiceProvider())
                ->register(CollectibleProofTestOverrides::class)
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
                    'status' => 'completed',
                    'ipfs_cid' => 'QmImageCid',
                    'metadata_cid' => 'QmMetadataCid',
                    'token_id' => 8,
                    'times_traded' => 0,
                    'created_at' => '2026-01-01 00:00:00',
                    'updated_at' => '2026-01-01 00:00:00',
                ],
                [
                    'id' => 2,
                    'owner_id' => 2,
                    'rarity' => 'common',
                    'status' => 'generating',
                    'ipfs_cid' => null,
                    'metadata_cid' => null,
                    'token_id' => null,
                    'times_traded' => 0,
                    'created_at' => '2026-01-01 00:00:00',
                    'updated_at' => '2026-01-01 00:00:00',
                ],
            ],
        ]);
    }

    /** @test */
    public function guest_can_fetch_proof_for_a_completed_collectible(): void
    {
        $response = $this->send($this->request('GET', '/api/collectibles/1/proof'));

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals('collectible-proof', $body['data']['type']);
        $this->assertEquals(1, $body['data']['attributes']['app']['collectibleId']);
        $this->assertEquals('verified', $body['data']['attributes']['chain']['state']);
    }

    /** @test */
    public function guest_cannot_fetch_proof_for_a_non_visible_collectible(): void
    {
        $response = $this->send($this->request('GET', '/api/collectibles/2/proof'));

        $this->assertEquals(404, $response->getStatusCode());
    }

    /** @test */
    public function owner_can_fetch_proof_for_their_non_public_collectible(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/collectibles/2/proof', [
                'authenticatedAs' => 2,
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals(2, $body['data']['attributes']['app']['collectibleId']);
        $this->assertEquals('not_minted', $body['data']['attributes']['chain']['state']);
    }
}

class CollectibleProofTestOverrides extends \Flarum\Foundation\AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(CollectibleProofServiceInterface::class, FakeCollectibleProofService::class);
    }
}
