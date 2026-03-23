<?php

namespace Donk\AigcCollectibles\Tests\integration\api;

use Donk\AigcCollectibles\Service\Contracts\NftMintingServiceInterface;
use Donk\AigcCollectibles\Service\Contracts\IPFSServiceInterface;
use Donk\AigcCollectibles\Tests\Fake\FakeNftMintingService;
use Donk\AigcCollectibles\Tests\Fake\FakeIPFSService;
use Flarum\Extend;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;

class MintCollectibleChainTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('donk-aigc-collectibles');

        $this->extend(
            (new Extend\ServiceProvider())
                ->register(MintTestServiceOverrides::class)
        );

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'noWalletUser', 'email' => 'nowallet@test.com', 'is_email_confirmed' => 1],
            ],
            'collectibles' => [
                [
                    'id' => 1,
                    'user_id' => 2,
                    'original_user_id' => 2,
                    'name' => 'Mintable Artifact',
                    'rarity' => 'rare',
                    'status' => 'completed',
                    'ipfs_cid' => 'QmTestImageCid',
                    'metadata_cid' => 'QmTestMetadataCid',
                    'aigc_prompt' => 'test prompt',
                    'token_id' => null,
                    'times_traded' => 0,
                    'created_at' => '2026-01-01 00:00:00',
                    'updated_at' => '2026-01-01 00:00:00',
                ],
                [
                    'id' => 2,
                    'user_id' => 2,
                    'original_user_id' => 2,
                    'name' => 'Already Minted',
                    'rarity' => 'epic',
                    'status' => 'completed',
                    'ipfs_cid' => 'QmMintedImage',
                    'metadata_cid' => 'QmMintedMetadata',
                    'aigc_prompt' => 'test prompt 2',
                    'token_id' => 999,
                    'times_traded' => 0,
                    'created_at' => '2026-01-01 00:00:00',
                    'updated_at' => '2026-01-01 00:00:00',
                ],
                [
                    'id' => 3,
                    'user_id' => 2,
                    'original_user_id' => 2,
                    'name' => 'No Metadata',
                    'rarity' => 'common',
                    'status' => 'generating',
                    'ipfs_cid' => null,
                    'metadata_cid' => null,
                    'aigc_prompt' => null,
                    'token_id' => null,
                    'times_traded' => 0,
                    'created_at' => '2026-01-01 00:00:00',
                    'updated_at' => '2026-01-01 00:00:00',
                ],
            ],
            'web3_accounts' => [
                ['id' => 1, 'user_id' => 2, 'address' => '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'source' => 'metamask', 'type' => 'evm', 'attached_at' => '2026-01-01 00:00:00', 'last_verified_at' => '2026-01-01 00:00:00'],
            ],
        ]);
    }

    /** @test */
    public function guest_cannot_mint(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/collectibles/1/mint')
        );

        $this->assertEquals(401, $response->getStatusCode());
    }

    /** @test */
    public function user_can_mint_completed_collectible_with_wallet(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/collectibles/1/mint', [
                'authenticatedAs' => 2,
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals('collectibles', $body['data']['type']);
        $this->assertNotNull($body['data']['attributes']['tokenId']);

        // Verify token_id was persisted
        $collectible = $this->database()->table('collectibles')->where('id', 1)->first();
        $this->assertNotNull($collectible->token_id);

        // Verify collectible_event was logged
        $event = $this->database()->table('collectible_events')
            ->where('collectible_id', 1)
            ->where('event_type', 'minted')
            ->first();
        $this->assertNotNull($event);
    }

    /** @test */
    public function cannot_mint_already_minted_collectible(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/collectibles/2/mint', [
                'authenticatedAs' => 2,
            ])
        );

        // Policy denies minting if token_id is already set
        $this->assertEquals(403, $response->getStatusCode());
    }

    /** @test */
    public function user_without_wallet_cannot_mint(): void
    {
        // Give user 3 a collectible to mint
        $this->database()->table('collectibles')->insert([
            'id' => 10,
            'user_id' => 3,
            'original_user_id' => 3,
            'name' => 'User3 Collectible',
            'rarity' => 'common',
            'status' => 'completed',
            'ipfs_cid' => 'QmUser3Image',
            'metadata_cid' => 'QmUser3Metadata',
            'token_id' => null,
            'times_traded' => 0,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);

        $response = $this->send(
            $this->request('POST', '/api/collectibles/10/mint', [
                'authenticatedAs' => 3,
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    /** @test */
    public function cannot_mint_other_users_collectible(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/collectibles/1/mint', [
                'authenticatedAs' => 3,
            ])
        );

        // Policy denies: user 3 doesn't own collectible 1
        $this->assertEquals(403, $response->getStatusCode());
    }
}

class MintTestServiceOverrides extends \Flarum\Foundation\AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(NftMintingServiceInterface::class, FakeNftMintingService::class);
        $this->container->singleton(IPFSServiceInterface::class, FakeIPFSService::class);
    }
}
