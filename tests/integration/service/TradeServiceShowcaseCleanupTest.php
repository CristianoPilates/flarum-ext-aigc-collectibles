<?php

namespace Donk\AigcCollectibles\Tests\integration\service;

use Donk\AigcCollectibles\Model\Trade;
use Donk\AigcCollectibles\Service\Contracts\TradeServiceInterface;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;

class TradeServiceShowcaseCleanupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('donk-aigc-collectibles');

        $this->prepareDatabase([
            'users' => [
                [
                    'id' => 3,
                    'username' => 'showcaseSeller',
                    'email' => 'showcase-seller@test.com',
                    'is_email_confirmed' => 1,
                    'blind_box_count' => 0,
                    'showcase_collectible_id' => 2,
                ],
                [
                    'id' => 4,
                    'username' => 'showcaseBuyer',
                    'email' => 'showcase-buyer@test.com',
                    'is_email_confirmed' => 1,
                    'blind_box_count' => 5,
                ],
            ],
            'collectibles' => [
                [
                    'id' => 2,
                    'owner_id' => 3,
                    'rarity' => 'rare',
                    'status' => 'completed',
                    'ipfs_cid' => 'QmTradeImage',
                    'metadata_cid' => 'QmTradeMeta',
                    'token_id' => 42,
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
                    'offered_boxes' => 2,
                    'status' => 'pending',
                    'created_at' => '2026-01-15 00:00:00',
                    'updated_at' => '2026-01-15 00:00:00',
                ],
            ],
            'blindboxes' => [
                [
                    'id' => 201,
                    'user_id' => 4,
                    'type' => 'trade_reward',
                    'seed' => str_repeat('a', 64),
                    'status' => 'unappraised',
                    'budget' => null,
                    'collectible_id' => null,
                    'created_at' => '2026-01-10 00:00:00',
                    'updated_at' => '2026-01-10 00:00:00',
                ],
                [
                    'id' => 202,
                    'user_id' => 4,
                    'type' => 'trade_reward',
                    'seed' => str_repeat('b', 64),
                    'status' => 'unappraised',
                    'budget' => null,
                    'collectible_id' => null,
                    'created_at' => '2026-01-11 00:00:00',
                    'updated_at' => '2026-01-11 00:00:00',
                ],
                [
                    'id' => 203,
                    'user_id' => 4,
                    'type' => 'trade_reward',
                    'seed' => str_repeat('c', 64),
                    'status' => 'unappraised',
                    'budget' => null,
                    'collectible_id' => null,
                    'created_at' => '2026-01-12 00:00:00',
                    'updated_at' => '2026-01-12 00:00:00',
                ],
                [
                    'id' => 204,
                    'user_id' => 4,
                    'type' => 'trade_reward',
                    'seed' => str_repeat('d', 64),
                    'status' => 'unappraised',
                    'budget' => null,
                    'collectible_id' => null,
                    'created_at' => '2026-01-13 00:00:00',
                    'updated_at' => '2026-01-13 00:00:00',
                ],
                [
                    'id' => 205,
                    'user_id' => 4,
                    'type' => 'trade_reward',
                    'seed' => str_repeat('e', 64),
                    'status' => 'unappraised',
                    'budget' => null,
                    'collectible_id' => null,
                    'created_at' => '2026-01-14 00:00:00',
                    'updated_at' => '2026-01-14 00:00:00',
                ],
            ],
        ]);
    }

    /** @test */
    public function accepting_a_trade_clears_the_sellers_showcase_if_the_item_was_traded(): void
    {
        $container = $this->app()->getContainer();

        /** @var TradeServiceInterface $service */
        $service = $container->make(TradeServiceInterface::class);

        /** @var Trade $trade */
        $trade = Trade::query()->findOrFail(100);
        /** @var User $seller */
        $seller = User::query()->findOrFail(3);

        $service->acceptTrade($trade, $seller);

        $seller = User::query()->findOrFail(3);
        $buyer = User::query()->findOrFail(4);

        $this->assertNull($seller->showcase_collectible_id);
        $this->assertSame(2, (int) $seller->blind_box_count);
        $this->assertSame(3, (int) $buyer->blind_box_count);

        $transferredBoxCount = $this->database()->table('blindboxes')
            ->where('user_id', 3)
            ->where('status', 'unappraised')
            ->count();

        $remainingBuyerBoxes = $this->database()->table('blindboxes')
            ->where('user_id', 4)
            ->where('status', 'unappraised')
            ->count();

        $this->assertSame(2, $transferredBoxCount);
        $this->assertSame(3, $remainingBuyerBoxes);

        $collectible = $this->database()->table('collectibles')->where('id', 2)->first();
        $this->assertSame(4, (int) $collectible->owner_id);
        $this->assertSame(1, (int) $collectible->times_traded);
    }
}
