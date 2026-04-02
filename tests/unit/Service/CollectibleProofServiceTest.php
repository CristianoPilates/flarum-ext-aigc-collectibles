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
use PHPUnit\Framework\Attributes\Test;

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

    #[Test]
    public function it_reports_not_configured_chain_state_when_blockchain_settings_are_missing(): void
    {
        $this->settings->shouldReceive('get')
            ->with('donk-aigc-collectibles.blockchain-rpc-url')
            ->andReturn('');

        $collectible = new Collectible();
        $collectible->id = 45;
        $collectible->owner_id = 2;
        $collectible->rarity = 'rare';
        $collectible->status = Collectible::STATUS_COMPLETED;
        $collectible->token_id = 12;
        $collectible->setRelation('owner', (new User())->forceFill(['username' => 'admin']));

        $proof = $this->service->buildProof($collectible);

        $this->assertFalse($proof['chain']['available']);
        $this->assertSame('not_configured', $proof['chain']['state']);
        $this->assertSame(12, $proof['chain']['tokenId']);
        $this->assertStringContainsString('not configured', $proof['chain']['error']);
    }

    #[Test]
    public function it_reports_metadata_errors_when_gateway_json_is_invalid(): void
    {
        $collectible = new Collectible();
        $collectible->id = 46;
        $collectible->owner_id = 2;
        $collectible->rarity = 'common';
        $collectible->status = Collectible::STATUS_COMPLETED;
        $collectible->metadata_cid = 'QmMetadataCidInvalid';
        $collectible->setRelation('owner', (new User())->forceFill(['username' => 'admin']));

        $this->client->shouldReceive('get')
            ->once()
            ->with('http://127.0.0.1:8888/ipfs/QmMetadataCidInvalid', Mockery::type('array'))
            ->andReturn(new Response(200, ['Content-Type' => 'application/json'], '{invalid-json'));

        $proof = $this->service->buildProof($collectible);

        $this->assertFalse($proof['metadata']['available']);
        $this->assertSame('error', $proof['metadata']['state']);
        $this->assertStringContainsString('Metadata JSON is invalid', $proof['metadata']['error']);
        $this->assertFalse($proof['image']['available']);
        $this->assertSame('missing', $proof['image']['state']);
    }

    #[Test]
    public function it_uses_metadata_image_as_image_source_when_collectible_ipfs_cid_is_missing(): void
    {
        $collectible = new Collectible();
        $collectible->id = 47;
        $collectible->owner_id = 2;
        $collectible->rarity = 'common';
        $collectible->status = Collectible::STATUS_COMPLETED;
        $collectible->metadata_cid = 'QmMetadataCidFromJson';
        $collectible->setRelation('owner', (new User())->forceFill(['username' => 'admin']));

        $this->client->shouldReceive('get')
            ->once()
            ->with('http://127.0.0.1:8888/ipfs/QmMetadataCidFromJson', Mockery::type('array'))
            ->andReturn(new Response(
                200,
                ['Content-Type' => 'application/json'],
                '{"name":"Collectible #47","image":"https://gateway.example/ipfs/QmImageCidFromMetadata?filename=image.png"}'
            ));

        $proof = $this->service->buildProof($collectible);

        $this->assertTrue($proof['metadata']['available']);
        $this->assertSame('QmImageCidFromMetadata', $proof['metadata']['imageCid']);
        $this->assertTrue($proof['image']['available']);
        $this->assertSame('metadata.image', $proof['image']['source']);
        $this->assertSame('QmImageCidFromMetadata', $proof['image']['cid']);
        $this->assertTrue($proof['image']['matchesMetadataImage']);
    }

    #[Test]
    public function it_converts_ipfs_urls_to_gateway_urls_when_possible(): void
    {
        $this->assertSame(
            'http://127.0.0.1:8888/ipfs/QmGatewayCid',
            $this->invokeHiddenMethod('toGatewayUrl', ['ipfs://QmGatewayCid'])
        );

        $this->assertSame(
            'http://127.0.0.1:8888/ipfs/bafyGatewayCid',
            $this->invokeHiddenMethod('toGatewayUrl', ['https://gateway.example/ipfs/bafyGatewayCid?download=1'])
        );

        $this->assertSame(
            'https://example.com/not-ipfs',
            $this->invokeHiddenMethod('toGatewayUrl', ['https://example.com/not-ipfs'])
        );
    }

    private function invokeHiddenMethod(string $method, array $args): mixed
    {
        $invoker = \Closure::bind(
            function (array $args) use ($method) {
                return $this->{$method}(...$args);
            },
            $this->service,
            CollectibleProofService::class
        );

        return $invoker($args);
    }
}
