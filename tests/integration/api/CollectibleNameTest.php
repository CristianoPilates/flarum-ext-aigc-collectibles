<?php

namespace Donk\AigcCollectibles\Tests\integration\api;

use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CollectibleNameTest extends TestCase
{
    private const OWNER_ID = 501;
    private const STRANGER_ID = 502;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extension('donk-aigc-collectibles');

        $this->prepareDatabase([
            'users' => [
                [
                    'id' => self::OWNER_ID,
                    'username' => 'collectibleOwner',
                    'email' => 'owner@test.com',
                    'is_email_confirmed' => 1,
                ],
                [
                    'id' => self::STRANGER_ID,
                    'username' => 'stranger',
                    'email' => 'stranger@test.com',
                    'is_email_confirmed' => 1,
                ],
            ],
            'collectibles' => [
                [
                    'id' => 1001,
                    'owner_id' => self::OWNER_ID,
                    'rarity' => 'rare',
                    'status' => 'completed',
                    'ipfs_cid' => 'QmTest',
                    'metadata_cid' => 'QmTestMeta',
                    'token_id' => null,
                    'times_traded' => 0,
                    'created_at' => '2026-01-01 00:00:00',
                    'updated_at' => '2026-01-01 00:00:00',
                ],
                [
                    'id' => 1002,
                    'owner_id' => self::STRANGER_ID,
                    'rarity' => 'epic',
                    'status' => 'completed',
                    'ipfs_cid' => 'QmOther',
                    'metadata_cid' => 'QmOtherMeta',
                    'token_id' => null,
                    'times_traded' => 0,
                    'created_at' => '2026-01-01 00:00:00',
                    'updated_at' => '2026-01-01 00:00:00',
                ],
            ],
        ]);
    }

    #[Test]
    public function owner_can_read_name_via_api(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/collectibles/1001')
                ->withAttribute('actorId', self::OWNER_ID)
        );

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        $body = json_decode((string) $response->getBody(), true);
        // Without name set, accessor returns fallback
        $this->assertSame('Collectible #1001', $body['data']['attributes']['name']);
    }

    #[Test]
    public function owner_can_update_own_name(): void
    {
        $response = $this->send(
            $this->request('PATCH', '/api/collectibles/1001', [
                'authenticatedAs' => self::OWNER_ID,
                'json' => [
                    'data' => [
                        'type' => 'collectibles',
                        'id' => '1001',
                        'attributes' => [
                            'name' => 'Cosmic Fox',
                        ],
                    ],
                ],
            ])
        );

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('Cosmic Fox', $body['data']['attributes']['name']);

        // Verify DB was persisted
        $row = $this->database()->table('collectibles')->where('id', 1001)->first();
        $this->assertSame('Cosmic Fox', $row->name);
    }

    #[Test]
    public function stranger_cannot_update_others_name(): void
    {
        $response = $this->send(
            $this->request('PATCH', '/api/collectibles/1001', [
                'authenticatedAs' => self::STRANGER_ID,
                'json' => [
                    'data' => [
                        'type' => 'collectibles',
                        'id' => '1001',
                        'attributes' => [
                            'name' => 'Stolen Name',
                        ],
                    ],
                ],
            ])
        );

        $this->assertNotSame(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertNotSame(201, $response->getStatusCode(), (string) $response->getBody());

        // Verify DB was NOT changed
        $row = $this->database()->table('collectibles')->where('id', 1001)->first();
        $this->assertNotSame('Stolen Name', $row->name ?? null);
    }

    #[Test]
    public function owner_can_clear_name_back_to_null(): void
    {
        // First set a name
        $this->database()->table('collectibles')->where('id', 1001)->update(['name' => 'Old Name']);

        // Then clear it — sends null to API
        $response = $this->send(
            $this->request('PATCH', '/api/collectibles/1001', [
                'authenticatedAs' => self::OWNER_ID,
                'json' => [
                    'data' => [
                        'type' => 'collectibles',
                        'id' => '1001',
                        'attributes' => [
                            'name' => null,
                        ],
                    ],
                ],
            ])
        );

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        // API returns fallback (accessor runs on null DB value)
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('Collectible #1001', $body['data']['attributes']['name']);

        // DB stores null
        $row = $this->database()->table('collectibles')->where('id', 1001)->first();
        $this->assertNull($row->name);
    }
}
