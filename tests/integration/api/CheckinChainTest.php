<?php

namespace Donk\AigcCollectibles\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;

class CheckinChainTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('donk-aigc-collectibles');

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'checkedUser', 'email' => 'checked@test.com', 'is_email_confirmed' => 1, 'blind_box_count' => 0],
            ],
            'checkin_records' => [
                ['id' => 1, 'user_id' => 3, 'reward_amount' => 1, 'checked_in_at' => Carbon::today()->toDateTimeString()],
            ],
        ]);
    }

    /** @test */
    public function guest_cannot_checkin(): void
    {
        $request = $this->request('POST', '/api/checkin-records/checkin')
            ->withAttribute('bypassCsrfToken', true);

        $response = $this->send($request);
        $this->assertSame(401, $response->getStatusCode(), (string) $response->getBody());

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame('not_authenticated', $payload['errors'][0]['code'] ?? null);
    }

    /** @test */
    public function user_can_checkin_and_receives_blind_boxes(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/checkin-records/checkin', [
                'authenticatedAs' => 2,
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);

        $this->assertArrayHasKey('data', $body);
        $this->assertEquals('checkin-records', $body['data']['type']);
        $this->assertNotEmpty($body['data']['attributes']['rewardAmount']);

        // Verify the user's blind_box_count was incremented in DB
        $user = $this->database()->table('users')->where('id', 2)->first();
        $this->assertGreaterThan(0, $user->blind_box_count);

        // Verify a checkin record was created
        $records = $this->database()->table('checkin_records')->where('user_id', 2)->get();
        $this->assertCount(1, $records);
    }

    /** @test */
    public function user_cannot_checkin_twice_on_same_day(): void
    {
        // User 3 already has a checkin record for today
        $response = $this->send(
            $this->request('POST', '/api/checkin-records/checkin', [
                'authenticatedAs' => 3,
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertStringContainsString('already checked in', $body['errors'][0]['detail'] ?? '');
    }

    /** @test */
    public function checkin_reward_respects_settings(): void
    {
        $this->setting('donk-aigc-collectibles.checkin-reward', 5);

        $response = $this->send(
            $this->request('POST', '/api/checkin-records/checkin', [
                'authenticatedAs' => 2,
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals(5, $body['data']['attributes']['rewardAmount']);

        $user = $this->database()->table('users')->where('id', 2)->first();
        $this->assertEquals(5, $user->blind_box_count);
    }

    /** @test */
    public function checkin_updates_last_checkin_at(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/checkin-records/checkin', [
                'authenticatedAs' => 2,
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $user = $this->database()->table('users')->where('id', 2)->first();
        $this->assertNotNull($user->last_checkin_at);
    }
}
