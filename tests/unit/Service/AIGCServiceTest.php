<?php

namespace Donk\AigcCollectibles\Tests\unit\Service;

use Donk\AigcCollectibles\Service\AIGCService;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\unit\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

class AIGCServiceTest extends TestCase
{
    /**
     * @var SettingsRepositoryInterface|MockInterface
     */
    protected $settingsMock;

    protected AIGCService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settingsMock = Mockery::mock(SettingsRepositoryInterface::class);

        $this->settingsMock->shouldReceive('get')
            ->with('donk-aigc-collectibles.aigc-api-url')
            ->andReturn('https://api.example.com/v1/images/generations')
            ->byDefault();

        $this->settingsMock->shouldReceive('get')
            ->with('donk-aigc-collectibles.aigc-api-key')
            ->andReturn('sk-123456')
            ->byDefault();

        $this->service = new AIGCService($this->settingsMock);
    }

    /**
     * 辅助方法：创建一个带有伪造 HTTP 响应的 AIGCService 实例
     */
    protected function createServiceWithMockedHttp(array $mockResponses): AIGCService
    {
        $mockHandler = new MockHandler($mockResponses);
        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        return new AIGCService($this->settingsMock, $client);
    }

    // ==========================================
    // 测试 isConfigured()
    // ==========================================

    #[Test]
    public function it_returns_true_when_api_is_fully_configured(): void
    {
        $this->assertTrue($this->service->isConfigured());
    }

    #[Test]
    public function it_returns_false_when_api_key_is_missing(): void
    {
        // 覆盖 setUp 中的默认行为，模拟 Key 为空
        $this->settingsMock->shouldReceive('get')
            ->with('donk-aigc-collectibles.aigc-api-key')
            ->andReturn('');

        $this->assertFalse($this->service->isConfigured());
    }

    // ==========================================
    // 测试 buildPrompt() (使用反射)
    // ==========================================

    #[Test]
    public function it_builds_the_correct_prompt_based_on_rarity(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'buildPrompt');
        // 测试 common (默认兜底)
        $commonResult = $reflection->invoke($this->service, 'A cute donk', 'common');
        $this->assertSame('A cute donk, simple, clean digital art style, collectible card art, centered composition, no text', $commonResult);

        // 测试 epic
        $epicResult = $reflection->invoke($this->service, 'A cute donk', 'epic');
        $this->assertSame('A cute donk, highly detailed, dramatic lighting, epic digital painting, collectible card art, centered composition, no text', $epicResult);

        // 测试未知的稀有度 (应该回退到 common)
        $unknownResult = $reflection->invoke($this->service, 'A cute donk', 'unknown_rarity');
        $this->assertSame('A cute donk, simple, clean digital art style, collectible card art, centered composition, no text', $unknownResult);
    }

    // ==========================================
    // 测试 generateImage()
    // ==========================================

    #[Test]
    public function it_returns_a_fallback_image_if_api_is_not_configured_when_generating_image(): void
    {
        // 覆盖配置，模拟未配置
        $this->settingsMock->shouldReceive('get')
            ->with('donk-aigc-collectibles.aigc-api-url')
            ->andReturn('');

        $result = $this->service->generateImage('A cute donk', 'common');

        $this->assertStringContainsString('<svg', $result);
        $this->assertStringContainsString('AIGC COLLECTIBLE', $result);
        $this->assertStringContainsString('FALLBACK RENDER', $result);
        $this->assertStringContainsString('COMMON', $result);
    }

    #[Test]
    public function it_generates_an_image_successfully(): void
    {
        $fakeImageContent = 'fake-image-binary-data';
        $fakeBase64 = base64_encode($fakeImageContent);

        $mockResponseBody = json_encode([
            'data' => [['b64_json' => $fakeBase64]],
        ]);

        // 使用辅助方法注入一个返回 200 成功的假 HTTP Client
        $serviceWithMockedHttp = $this->createServiceWithMockedHttp([
            new Response(200, ['Content-Type' => 'application/json'], $mockResponseBody),
        ]);

        $result = $serviceWithMockedHttp->generateImage('A cute donk', 'rare');

        $this->assertSame($fakeImageContent, $result);
    }

    #[Test]
    public function it_throws_exception_on_unexpected_api_response_format(): void
    {
        // 模拟 API 返回了 200，但是 JSON 结构不对（缺少 b64_json 字段）
        $badResponseBody = json_encode([
            'data' => [['url' => 'https://example.com/image.png']], // 比如 OpenAI 返回了 url 而不是 b64_json
        ]);

        $serviceWithMockedHttp = $this->createServiceWithMockedHttp([
            new Response(200, ['Content-Type' => 'application/json'], $badResponseBody),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected AIGC API response format.');

        $serviceWithMockedHttp->generateImage('A cute donk', 'common');
    }

    #[Test]
    public function it_returns_a_fallback_image_on_api_request_failure(): void
    {
        // 模拟 Guzzle 抛出网络异常 (如超时、401 未授权、500 服务器错误等)
        $serviceWithMockedHttp = $this->createServiceWithMockedHttp([
            new RequestException('Error Communicating with Server', new Request('POST', 'test')),
        ]);

        $result = $serviceWithMockedHttp->generateImage('A cute donk', 'common');

        $this->assertStringContainsString('<svg', $result);
        $this->assertStringContainsString('AIGC COLLECTIBLE', $result);
        $this->assertStringContainsString('FALLBACK RENDER', $result);
        $this->assertStringContainsString('COMMON', $result);
    }
}
