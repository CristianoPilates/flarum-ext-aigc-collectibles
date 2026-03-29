<?php

namespace Donk\AigcCollectibles\Tests\unit\Service;

use Donk\AigcCollectibles\Model\Collectible;
use Donk\AigcCollectibles\Service\CollectibleProofService;
use Donk\AigcCollectibles\Service\Contracts\IPFSServiceInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\unit\TestCase;
use Flarum\User\User;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Mockery\MockInterface;

class CollectibleProofServiceTest extends TestCase
{
    /** @var SettingsRepositoryInterface&MockInterface */
    protected $settings;

    /** @var Client&MockInterface */
    protected $client;

    /** @var IPFSServiceInterface&MockInterface */
    protected $ipfs;

    protected CollectibleProofService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = Mockery::mock(SettingsRepositoryInterface::class);
        $this->client = Mockery::mock(Client::class);

        $this->settings->shouldReceive('get')
            ->with('donk-aigc-collectibles.ipfs-gateway-url', 'https://ipfs.io/ipfs/')
            ->andReturn('http://127.0.0.1:8888/ipfs/')
            ->byDefault();

        $this->settings->shouldReceive('get')
            ->with('donk-aigc-collectibles.blockchain-rpc-url')
            ->andReturn('http://127.0.0.1:8545')
            ->byDefault();

        $this->settings->shouldReceive('get')
            ->with('donk-aigc-collectibles.nft-contract-address')
            ->andReturn('0x1234567890abcdef1234567890abcdef12345678')
            ->byDefault();

        $this->ipfs = Mockery::mock(IPFSServiceInterface::class);
        $this->ipfs->shouldReceive('getGatewayUrl')
            ->andReturnUsing(fn (string $cid): string => 'http://127.0.0.1:8888/ipfs/' . $cid);

        $this->service = new CollectibleProofService($this->settings, $this->ipfs, $this->client);
    }

    /** @test */
    public function it_builds_a_complete_four_layer_proof_for_a_minted_collectible(): void
    {
        $metadataJson = json_encode([
            'name' => 'Collectible #43',
            'image' => 'ipfs://QmImageCid',
            'attributes' => [
                ['trait_type' => 'rarity', 'value' => 'Rare'],
            ],
        ], JSON_UNESCAPED_SLASHES);

        $this->client->shouldReceive('get')
            ->once()
            ->with('http://127.0.0.1:8888/ipfs/QmMetadataCid', Mockery::type('array'))
            ->andReturn(new Response(200, ['Content-Type' => 'application/json'], $metadataJson));

        $this->client->shouldReceive('post')
            ->times(3)
            ->andReturn(
                new Response(200, [], '{"jsonrpc":"2.0","id":1,"result":"0x7a69"}'),
                new Response(200, [], '{"jsonrpc":"2.0","id":1,"result":"0x000000000000000000000000abb6bc9ec4c33cf50b4013ff8bb3b4855e35168f"}'),
                new Response(200, [], '{"jsonrpc":"2.0","id":1,"result":"0x00000000000000000000000000000000000000000000000000000000000000200000000000000000000000000000000000000000000000000000000000000015697066733a2f2f516d4d657461646174614369640000000000000000000000"}')
            );

        $collectible = new Collectible();
        $collectible->id = 43;
        $collectible->owner_id = 2;
        $collectible->rarity = 'rare';
        $collectible->status = Collectible::STATUS_COMPLETED;
        $collectible->ipfs_cid = 'QmImageCid';
        $collectible->metadata_cid = 'QmMetadataCid';
        $collectible->token_id = 8;
        $collectible->setRelation('owner', (new User())->forceFill(['username' => 'admin']));

        $proof = $this->service->buildProof($collectible);

        $this->assertTrue($proof['chain']['available']);
        $this->assertSame('0xabb6bc9ec4c33cf50b4013ff8bb3b4855e35168f', $proof['chain']['ownerOf']);
        $this->assertSame('ipfs://QmMetadataCid', $proof['chain']['tokenURI']);
        $this->assertTrue($proof['chain']['matchesMetadataCid']);

        $this->assertTrue($proof['metadata']['available']);
        $this->assertSame('QmImageCid', $proof['metadata']['imageCid']);
        $this->assertSame('http://127.0.0.1:8888/ipfs/QmImageCid', $proof['metadata']['imageGatewayUrl']);

        $this->assertTrue($proof['image']['available']);
        $this->assertTrue($proof['image']['matchesMetadataImage']);
        $this->assertSame('http://127.0.0.1:8888/ipfs/QmImageCid', $proof['image']['gatewayUrl']);
    }

    /** @test */
    public function it_reports_not_minted_chain_state_without_throwing(): void
    {
        $collectible = new Collectible();
        $collectible->id = 44;
        $collectible->owner_id = 2;
        $collectible->rarity = 'common';
        $collectible->status = Collectible::STATUS_COMPLETED;
        $collectible->ipfs_cid = 'QmImageCid44';
        $collectible->metadata_cid = 'QmMetadataCid44';
        $collectible->token_id = null;
        $collectible->setRelation('owner', (new User())->forceFill(['username' => 'admin']));

        $this->client->shouldReceive('get')
            ->once()
            ->andReturn(new Response(200, ['Content-Type' => 'application/json'], '{"name":"Collectible #44","image":"ipfs://QmImageCid44"}'));

        $proof = $this->service->buildProof($collectible);

        $this->assertFalse($proof['chain']['available']);
        $this->assertSame('not_minted', $proof['chain']['state']);
        $this->assertTrue($proof['metadata']['available']);
    }
}
