<?php

namespace Donk\AigcCollectibles\Tests\integration\api;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;

class TradeChainTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('donk-aigc-collectibles');

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'seller', 'email' => 'seller@test.com', 'is_email_confirmed' => 1, 'blind_box_count' => 5],
                ['id' => 4, 'username' => 'buyer', 'email' => 'buyer@test.com', 'is_email_confirmed' => 1, 'blind_box_count' => 20],
                ['id' => 5, 'username' => 'poorBuyer', 'email' => 'poor@test.com', 'is_email_confirmed' => 1, 'blind_box_count' => 0],
            ],
            'collectibles' => [
                [
                    'id' => 1,
                    'owner_id' => 3,
                    'rarity' => 'rare',
                    'status' => 'completed',
                    'ipfs_cid' => 'QmTradeImage',
                    'metadata_cid' => 'QmTradeMeta',
                    'token_id' => null,
                    'times_traded' => 0,
                    'created_at' => '2026-01-01 00:00:00',
                    'updated_at' => '2026-01-01 00:00:00',
                ],
                [
                    'id' => 2,
                    'owner_id' => 3,
                    'rarity' => 'epic',
                    'status' => 'completed',
                    'ipfs_cid' => 'QmAnotherImage',
                    'metadata_cid' => 'QmAnotherMeta',
                    'token_id' => null,
                    'times_traded' => 0,
                    'created_at' => '2026-01-01 00:00:00',
                    'updated_at' => '2026-01-01 00:00:00',
                ],
            ],
            'trades' => [
                [
                    'id' => 100,
                    'from_user_id' => 4,
                    'to_user_id' => 3,
                    'collectible_id' => 2,
                    'offered_boxes' => 5,
                    'status' => 'pending',
                    'note' => 'existing offer',
                    'created_at' => '2026-01-15 00:00:00',
                    'updated_at' => '2026-01-15 00:00:00',
                ],
            ],
        ]);
    }

    // ─── CREATE TRADE ───

    /** @test */
    public function guest_cannot_create_trade(): void
    {
        $request = $this->request('POST', '/api/trades', [
            'json' => [
                'data' => [
                    'attributes' => [
                        'collectibleId' => 1,
                        'offeredBoxes' => 3,
                    ],
                ],
            ],
        ])->withAttribute('bypassCsrfToken', true);

        $response = $this->send($request);

        $this->assertEquals(401, $response->getStatusCode());
    }

    /** @test */
    public function buyer_can_create_trade_offer(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/trades', [
                'authenticatedAs' => 4,
                'json' => [
                    'data' => [
                        'attributes' => [
                            'collectibleId' => 1,
                            'offeredBoxes' => 3,
                            'note' => 'I want this!',
                        ],
                    ],
                ],
            ])
        );

        $this->assertContains($response->getStatusCode(), [200, 201]);

        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals('trades', $body['data']['type']);
        $this->assertEquals('pending', $body['data']['attributes']['status']);
        $this->assertEquals(3, $body['data']['attributes']['offeredBoxes']);

        // Verify trade was saved
        $trade = $this->database()->table('trades')
            ->where('from_user_id', 4)
            ->where('collectible_id', 1)
            ->first();
        $this->assertNotNull($trade);
        $this->assertEquals('pending', $trade->status);
        $this->assertEquals('I want this!', $trade->note);
    }

    /** @test */
    public function owner_cannot_trade_own_collectible(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/trades', [
                'authenticatedAs' => 3,
                'json' => [
                    'data' => [
                        'attributes' => [
                            'collectibleId' => 1,
                            'offeredBoxes' => 1,
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    /** @test */
    public function buyer_with_insufficient_boxes_cannot_create_trade(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/trades', [
                'authenticatedAs' => 5,
                'json' => [
                    'data' => [
                        'attributes' => [
                            'collectibleId' => 1,
                            'offeredBoxes' => 10,
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    // ─── ACCEPT TRADE ───

    /** @test */
    public function seller_can_accept_trade(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/trades/100/accept', [
                'authenticatedAs' => 3,
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals('completed', $body['data']['attributes']['status']);

        // Verify ownership transferred
        $collectible = $this->database()->table('collectibles')->where('id', 2)->first();
        $this->assertEquals(4, $collectible->owner_id);
        $this->assertEquals(1, $collectible->times_traded);

        // Verify blind box transfer: buyer -5, seller +5
        $buyer = $this->database()->table('users')->where('id', 4)->first();
        $seller = $this->database()->table('users')->where('id', 3)->first();
        $this->assertEquals(15, $buyer->blind_box_count);  // 20 - 5
        $this->assertEquals(10, $seller->blind_box_count);  // 5 + 5

        // Verify trade record updated
        $trade = $this->database()->table('trades')->where('id', 100)->first();
        $this->assertEquals('completed', $trade->status);
        $this->assertNotNull($trade->completed_at);

        // Verify collectible_event logged
        $event = $this->database()->table('collectible_events')
            ->where('collectible_id', 2)
            ->where('event_type', 'traded')
            ->first();
        $this->assertNotNull($event);
        $this->assertEquals(3, $event->from_user_id);
        $this->assertEquals(4, $event->to_user_id);
    }

    /** @test */
    public function buyer_cannot_accept_own_trade(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/trades/100/accept', [
                'authenticatedAs' => 4,
            ])
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    /** @test */
    public function accepting_trade_cancels_other_pending_trades_for_same_collectible(): void
    {
        // Create another pending trade for the same collectible (id=2)
        $this->database()->table('trades')->insert([
            'id' => 101,
            'from_user_id' => 2,
            'to_user_id' => 3,
            'collectible_id' => 2,
            'offered_boxes' => 2,
            'status' => 'pending',
            'created_at' => '2026-01-16 00:00:00',
            'updated_at' => '2026-01-16 00:00:00',
        ]);

        // Accept trade 100
        $response = $this->send(
            $this->request('POST', '/api/trades/100/accept', [
                'authenticatedAs' => 3,
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        // The other trade should be cancelled
        $otherTrade = $this->database()->table('trades')->where('id', 101)->first();
        $this->assertEquals('cancelled', $otherTrade->status);
    }

    // ─── REJECT TRADE ───

    /** @test */
    public function seller_can_reject_trade(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/trades/100/reject', [
                'authenticatedAs' => 3,
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals('rejected', $body['data']['attributes']['status']);

        // Verify no ownership or balance change
        $collectible = $this->database()->table('collectibles')->where('id', 2)->first();
        $this->assertEquals(3, $collectible->owner_id);

        $buyer = $this->database()->table('users')->where('id', 4)->first();
        $this->assertEquals(20, $buyer->blind_box_count);
    }

    /** @test */
    public function buyer_cannot_reject_trade(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/trades/100/reject', [
                'authenticatedAs' => 4,
            ])
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    // ─── CANCEL TRADE ───

    /** @test */
    public function buyer_can_cancel_own_trade(): void
    {
        $response = $this->send(
            $this->request('DELETE', '/api/trades/100', [
                'authenticatedAs' => 4,
            ])
        );

        $this->assertEquals(204, $response->getStatusCode());

        $trade = $this->database()->table('trades')->where('id', 100)->first();
        $this->assertEquals('cancelled', $trade->status);
        $this->assertNotNull($trade->completed_at);
    }

    /** @test */
    public function seller_cannot_cancel_trade(): void
    {
        $response = $this->send(
            $this->request('DELETE', '/api/trades/100', [
                'authenticatedAs' => 3,
            ])
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    // ─── FULL E2E FLOW ───

    /** @test */
    public function full_trade_lifecycle_create_then_accept(): void
    {
        // Step 1: Buyer creates offer on collectible 1
        $createResponse = $this->send(
            $this->request('POST', '/api/trades', [
                'authenticatedAs' => 4,
                'json' => [
                    'data' => [
                        'attributes' => [
                            'collectibleId' => 1,
                            'offeredBoxes' => 7,
                        ],
                    ],
                ],
            ])
        );

        $this->assertContains($createResponse->getStatusCode(), [200, 201]);
        $createBody = json_decode((string) $createResponse->getBody(), true);
        $tradeId = $createBody['data']['id'];

        // Step 2: Seller accepts the trade
        $acceptResponse = $this->send(
            $this->request('POST', "/api/trades/{$tradeId}/accept", [
                'authenticatedAs' => 3,
            ])
        );

        $this->assertEquals(200, $acceptResponse->getStatusCode());

        // Verify final state
        $collectible = $this->database()->table('collectibles')->where('id', 1)->first();
        $this->assertEquals(4, $collectible->owner_id);         // Buyer now owns it
        $this->assertEquals(1, $collectible->times_traded);

        $buyer = $this->database()->table('users')->where('id', 4)->first();
        $seller = $this->database()->table('users')->where('id', 3)->first();
        $this->assertEquals(13, $buyer->blind_box_count);  // 20 - 7
        $this->assertEquals(12, $seller->blind_box_count);  // 5 + 7
    }
}
