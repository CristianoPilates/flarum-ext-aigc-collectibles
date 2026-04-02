<?php

namespace Donk\AigcCollectibles\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CheckinChainTest extends TestCase
{
    private const USER_ID = 201;
    private const CHECKED_USER_ID = 202;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('donk-aigc-collectibles');

        $this->prepareDatabase([
            'users' => [
                [
                    'id' => self::USER_ID,
                    'username' => 'checkinUser',
                    'password' => '$2y$10$LO59tiT7uggl6Oe23o/O6.utnF6ipngYjvMvaxo1TciKqBttDNKim',
                    'email' => 'checkin-user@test.com',
                    'is_email_confirmed' => 1,
                    'blind_box_count' => 0,
                ],
                [
                    'id' => self::CHECKED_USER_ID,
                    'username' => 'checkedUser',
                    'email' => 'checked@test.com',
                    'is_email_confirmed' => 1,
                    'blind_box_count' => 0,
                    'last_checkin_at' => Carbon::today()->toDateTimeString(),
                ],
            ],
            'checkin_records' => [
                [
                    'id' => 1,
                    'user_id' => self::CHECKED_USER_ID,
                    'reward_amount' => 1,
                    'checked_in_at' => Carbon::today()->toDateTimeString(),
                ],
            ],
        ]);
    }

    #[Test]
    public function guest_cannot_checkin(): void
    {
        $request = $this->request('POST', '/api/checkin-records/checkin')
            ->withAttribute('bypassCsrfToken', true);

        $response = $this->send($request);
        $this->assertSame(401, $response->getStatusCode(), (string) $response->getBody());

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame('not_authenticated', $payload['errors'][0]['code'] ?? null);
    }

    #[Test]
    public function user_can_checkin_and_receives_blind_boxes(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/checkin-records/checkin', [
                'authenticatedAs' => self::USER_ID,
            ])
        );

        $this->assertSame(200, $response->getStatusCode());

        $body = $this->decodeJson($response);

        $this->assertArrayHasKey('data', $body);
        $this->assertSame('checkin-records', $body['data']['type']);
        $this->assertSame(1, $body['data']['attributes']['rewardAmount']);
        $this->assertSame(1, $body['data']['attributes']['blindBoxCount']);
        $this->assertNotEmpty($body['data']['attributes']['rewardAmount']);
        $this->assertNotEmpty($body['data']['attributes']['checkedInAt']);
        $this->assertNotEmpty($body['data']['attributes']['lastCheckinAt']);

        $user = $this->database()->table('users')->where('id', self::USER_ID)->first();
        $this->assertSame(1, (int) $user->blind_box_count);
        $this->assertNotNull($user->last_checkin_at);
        $this->assertTrue(Carbon::parse($user->last_checkin_at)->isToday());

        $records = $this->database()->table('checkin_records')->where('user_id', self::USER_ID)->get();
        $this->assertCount(1, $records);
        $this->assertSame(1, (int) $records[0]->reward_amount);

        $blindBoxes = $this->database()->table('blindboxes')->where('user_id', self::USER_ID)->get();
        $this->assertCount(1, $blindBoxes);
        $this->assertSame('checkin_reward', $blindBoxes[0]->type);
        $this->assertSame('unappraised', $blindBoxes[0]->status);
        $this->assertNotEmpty($blindBoxes[0]->seed);
    }

    #[Test]
    public function user_cannot_checkin_twice_on_same_day(): void
    {
        $initialBlindBoxCount = $this->database()->table('blindboxes')->where('user_id', self::CHECKED_USER_ID)->count();

        $response = $this->send(
            $this->request('POST', '/api/checkin-records/checkin', [
                'authenticatedAs' => self::CHECKED_USER_ID,
            ])
        );

        $this->assertSame(422, $response->getStatusCode());

        $body = $this->decodeJson($response);
        $this->assertStringContainsString('already checked in', $body['errors'][0]['detail'] ?? '');
        $this->assertSame(
            1,
            $this->database()->table('checkin_records')->where('user_id', self::CHECKED_USER_ID)->count()
        );
        $this->assertSame(
            $initialBlindBoxCount,
            $this->database()->table('blindboxes')->where('user_id', self::CHECKED_USER_ID)->count()
        );
    }

    #[Test]
    public function checkin_reward_respects_settings(): void
    {
        $this->setting('donk-aigc-collectibles.checkin-reward', 5);

        $response = $this->send(
            $this->request('POST', '/api/checkin-records/checkin', [
                'authenticatedAs' => self::USER_ID,
            ])
        );

        $this->assertSame(200, $response->getStatusCode());

        $body = $this->decodeJson($response);
        $this->assertSame(5, $body['data']['attributes']['rewardAmount']);
        $this->assertSame(5, $body['data']['attributes']['blindBoxCount']);

        $user = $this->database()->table('users')->where('id', self::USER_ID)->first();
        $this->assertSame(5, (int) $user->blind_box_count);
        $this->assertSame(
            5,
            $this->database()->table('blindboxes')->where('user_id', self::USER_ID)->count()
        );
        $this->assertSame(
            5,
            $this->database()->table('blindboxes')->where('user_id', self::USER_ID)->where('type', 'checkin_reward')->count()
        );
    }

    #[Test]
    public function checkin_updates_last_checkin_at(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/checkin-records/checkin', [
                'authenticatedAs' => self::USER_ID,
            ])
        );

        $this->assertSame(200, $response->getStatusCode());

        $user = $this->database()->table('users')->where('id', self::USER_ID)->first();
        $this->assertNotNull($user->last_checkin_at);
        $this->assertTrue(Carbon::parse($user->last_checkin_at)->isToday());
    }

    private function decodeJson($response): array
    {
        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
