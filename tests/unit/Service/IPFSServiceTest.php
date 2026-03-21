<?php

namespace Donk\AigcCollectibles\Tests\unit\Service;

use Donk\AigcCollectibles\Service\IPFSService;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\unit\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Mockery;
use Mockery\MockInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * @covers \Donk\AigcCollectibles\Service\IPFSService
 */
class IPFSServiceTest extends TestCase
{
    /** @var SettingsRepositoryInterface&MockInterface */
    protected $settings;

    /** @var Client&MockInterface */
    protected $client;

    protected IPFSService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = Mockery::mock(SettingsRepositoryInterface::class);
        $this->client = Mockery::mock(Client::class);

        $this->settings->shouldReceive('get')
            ->with('donk-aigc-collectibles.ipfs-api-url')
            ->andReturn('https://ipfs-api.example.com')
            ->byDefault();

        $this->service = new IPFSService($this->settings, $this->client);
    }

    /** @test */
    public function it_uploads_binary_data_and_returns_the_cid(): void
    {
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getBody->getContents')
            ->once()
            ->andReturn('{"Hash":"QmImageCid"}');

        $this->client->shouldReceive('post')
            ->once()
            ->with(
                'https://ipfs-api.example.com/api/v0/add',
                Mockery::on(fn (array $options): bool => $this->matchesAddRequestOptions($options, 'raw-image-data', 'collectible.png'))
            )
            ->andReturn($response);

        $cid = $this->service->upload('raw-image-data');

        $this->assertSame('QmImageCid', $cid);
    }

    /** @test */
    public function it_throws_when_upload_response_format_is_unexpected(): void
    {
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getBody->getContents')
            ->once()
            ->andReturn('{"ok":true}');

        $this->client->shouldReceive('post')
            ->once()
            ->andReturn($response);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected IPFS API response format.');

        $this->service->upload('raw-image-data');
    }

    /** @test */
    public function it_wraps_guzzle_exception_when_upload_fails(): void
    {
        $this->client->shouldReceive('post')
            ->once()
            ->andThrow(new RequestException('Network down', new Request('POST', 'https://ipfs-api.example.com/api/v0/add')));

        try {
            $this->service->upload('raw-image-data');
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('IPFS upload failed: Network down', $e->getMessage());
            $this->assertInstanceOf(RequestException::class, $e->getPrevious());
        }
    }

    /** @test */
    public function it_uploads_json_metadata_and_returns_the_cid(): void
    {
        $metadata = [
            'name' => '测试藏品',
            'image' => 'https://example.com/ipfs/QmImageCid',
        ];
        $expectedJson = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getBody->getContents')
            ->once()
            ->andReturn('{"Hash":"QmMetadataCid"}');

        $this->client->shouldReceive('post')
            ->once()
            ->with(
                'https://ipfs-api.example.com/api/v0/add',
                Mockery::on(fn (array $options): bool => $this->matchesAddRequestOptions($options, $expectedJson, 'metadata.json'))
            )
            ->andReturn($response);

        $cid = $this->service->uploadJson($metadata);

        $this->assertSame('QmMetadataCid', $cid);
    }

    /** @test */
    public function it_throws_when_metadata_upload_response_format_is_unexpected(): void
    {
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getBody->getContents')
            ->once()
            ->andReturn('{"ok":true}');

        $this->client->shouldReceive('post')
            ->once()
            ->andReturn($response);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected IPFS API response format.');

        $this->service->uploadJson(['name' => 'Collectible']);
    }

    /** @test */
    public function it_wraps_guzzle_exception_when_metadata_upload_fails(): void
    {
        $this->client->shouldReceive('post')
            ->once()
            ->andThrow(new RequestException('Timeout', new Request('POST', 'https://ipfs-api.example.com/api/v0/add')));

        try {
            $this->service->uploadJson(['name' => 'Collectible']);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('IPFS metadata upload failed: Timeout', $e->getMessage());
            $this->assertInstanceOf(RequestException::class, $e->getPrevious());
        }
    }

    /** @test */
    public function it_throws_when_api_url_is_not_configured(): void
    {
        $this->settings->shouldReceive('get')
            ->with('donk-aigc-collectibles.ipfs-api-url')
            ->andReturn('');

        $this->client->shouldNotReceive('post');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('IPFS API URL is not configured.');

        $this->service->upload('raw-image-data');
    }

    /** @test */
    public function it_builds_gateway_url_with_custom_setting_and_trims_slashes(): void
    {
        $this->settings->shouldReceive('get')
            ->once()
            ->with('donk-aigc-collectibles.ipfs-gateway-url', 'https://ipfs.io/ipfs/')
            ->andReturn('https://gateway.example.com/ipfs/');

        $gatewayUrl = $this->service->getGatewayUrl('QmGatewayCid');

        $this->assertSame('https://gateway.example.com/ipfs/QmGatewayCid', $gatewayUrl);
    }

    /** @test */
    public function it_can_use_partial_mock_and_chain_mock_for_metadata_upload(): void
    {
        $metadata = ['name' => 'Partial'];
        $expectedJson = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getBody->getContents')
            ->once()
            ->andReturn('{"Hash":"QmPartialCid"}');

        $partialClient = Mockery::mock(Client::class);
        $partialClient->shouldReceive('post')
            ->once()
            ->with(
                'https://partial-api.example.com/api/v0/add',
                Mockery::on(fn (array $options): bool => $this->matchesAddRequestOptions($options, $expectedJson, 'metadata.json'))
            )
            ->andReturn($response);

        /** @var IPFSService&MockInterface $partialService */
        $partialService = Mockery::mock(IPFSService::class, [$this->settings, $partialClient])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $partialService->shouldReceive('getApiUrl')
            ->once()
            ->andReturn('https://partial-api.example.com');

        $cid = $partialService->uploadJson($metadata);

        $this->assertSame('QmPartialCid', $cid);
    }

    /**
     * Validate the expected payload for IPFS `/api/v0/add` requests.
     */
    protected function matchesAddRequestOptions(array $actual, string $contents, string $filename): bool
    {
        return $actual === [
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => $contents,
                    'filename' => $filename,
                ],
            ],
            'query' => [
                'pin' => 'false',
            ],
        ];
    }
}
