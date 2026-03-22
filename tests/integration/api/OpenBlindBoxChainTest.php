<?php

namespace Donk\AigcCollectibles\Tests\integration\api;

use Donk\AigcCollectibles\Service\Contracts\AIGCServiceInterface;
use Donk\AigcCollectibles\Service\Contracts\IPFSServiceInterface;
use Donk\AigcCollectibles\Tests\Fake\FakeAIGCService;
use Donk\AigcCollectibles\Tests\Fake\FakeIPFSService;
use Flarum\Extend;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;

class OpenBlindBoxChainTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('donk-aigc-collectibles');

        // Override external services with fakes
        $this->extend(
            (new Extend\ServiceProvider())
                ->register(TestServiceOverrides::class)
        );

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'richUser', 'email' => 'rich@test.com', 'is_email_confirmed' => 1, 'blind_box_count' => 10],
                ['id' => 4, 'username' => 'poorUser', 'email' => 'poor@test.com', 'is_email_confirmed' => 1, 'blind_box_count' => 0],
            ],
        ]);
    }

    /** @test */
    public function guest_cannot_open_blind_box(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/collectibles/generate')
        );

        $this->assertEquals(401, $response->getStatusCode());
    }

    /** @test */
    public function user_with_boxes_can_open_blind_box(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/collectibles/generate', [
                'authenticatedAs' => 3,
            ])
        );

        // Should succeed — 200 or 201
        $this->assertContains($response->getStatusCode(), [200, 201]);

        $body = json_decode((string) $response->getBody(), true);

        $this->assertArrayHasKey('data', $body);
        $this->assertEquals('collectibles', $body['data']['type']);
        $this->assertEquals('generating', $body['data']['attributes']['status']);
        $this->assertContains($body['data']['attributes']['rarity'], ['common', 'rare', 'epic', 'legendary']);

        // Verify blind_box_count was decremented
        $user = $this->database()->table('users')->where('id', 3)->first();
        $this->assertEquals(9, $user->blind_box_count);

        // Verify collectible record was created in DB
        $collectible = $this->database()->table('collectibles')->where('user_id', 3)->first();
        $this->assertNotNull($collectible);
        $this->assertEquals('generating', $collectible->status);
        $this->assertEquals(3, $collectible->original_user_id);

        // Verify collectible_event was logged
        $event = $this->database()->table('collectible_events')
            ->where('collectible_id', $collectible->id)
            ->where('event_type', 'generated')
            ->first();
        $this->assertNotNull($event);
    }

    /** @test */
    public function user_without_boxes_cannot_open_blind_box(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/collectibles/generate', [
                'authenticatedAs' => 4,
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());

        // Verify no collectible was created
        $count = $this->database()->table('collectibles')->where('user_id', 4)->count();
        $this->assertEquals(0, $count);
    }

    /** @test */
    public function opening_box_creates_correct_original_user_id(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/collectibles/generate', [
                'authenticatedAs' => 3,
            ])
        );

        $this->assertContains($response->getStatusCode(), [200, 201]);

        $collectible = $this->database()->table('collectibles')->where('user_id', 3)->first();
        $this->assertEquals(3, $collectible->user_id);
        $this->assertEquals(3, $collectible->original_user_id);
    }

    /** @test */
    public function opening_multiple_boxes_decrements_correctly(): void
    {
        // Open first box
        $this->send(
            $this->request('POST', '/api/collectibles/generate', [
                'authenticatedAs' => 3,
            ])
        );

        // Open second box
        $this->send(
            $this->request('POST', '/api/collectibles/generate', [
                'authenticatedAs' => 3,
            ])
        );

        $user = $this->database()->table('users')->where('id', 3)->first();
        $this->assertEquals(8, $user->blind_box_count);

        $count = $this->database()->table('collectibles')->where('user_id', 3)->count();
        $this->assertEquals(2, $count);
    }
}

/**
 * Service provider that overrides external services with fakes for integration testing.
 */
class TestServiceOverrides extends \Flarum\Foundation\AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(AIGCServiceInterface::class, FakeAIGCService::class);
        $this->container->singleton(IPFSServiceInterface::class, FakeIPFSService::class);
    }
}
