<?php

namespace Donk\AigcCollectibles\Tests\unit\Service;

use Carbon\Carbon;
use Donk\AigcCollectibles\Event\TradeCompleted;
use Donk\AigcCollectibles\Event\TradeCreated;
use Donk\AigcCollectibles\Model\Collectible;
use Donk\AigcCollectibles\Model\Trade;
use Donk\AigcCollectibles\Service\BlindBoxService;
use Donk\AigcCollectibles\Service\TradeService;
use Flarum\Foundation\ValidationException;
use Flarum\Testing\unit\TestCase;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Mockery;
use Mockery\MockInterface;

/**
 * Unit tests for {@see TradeService}.
 *
 * Tests are split into two groups:
 *
 * 1. **Simple validation & state-change tests** — guard clauses and property
 *    mutations on reject/cancel/accept flows using partial mocks.
 *
 * 2. **Static-call tests** (`@runInSeparateProcess`) — `createOffer()` and
 *    the full `acceptTrade()` flow that internally call Eloquent static
 *    methods. Alias mocks intercept those static call chains.
 */
class TradeServiceTest extends TestCase
{
    /** @var BlindBoxService&MockInterface */
    protected $blindBoxService;

    /** @var ConnectionInterface&MockInterface */
    protected $db;

    /** @var Dispatcher&MockInterface */
    protected $events;

    protected TradeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->blindBoxService = Mockery::mock(BlindBoxService::class);
        $this->db = Mockery::mock(ConnectionInterface::class);
        $this->events = Mockery::mock(Dispatcher::class);

        $this->service = new TradeService(
            $this->blindBoxService,
            $this->db,
            $this->events
        );
    }

    // =====================================================================
    // rejectTrade()
    // =====================================================================

    /**
     * @test
     *
     * Seller rejects a pending trade — status becomes "rejected",
     * completed_at is set, and a TradeCompleted event is dispatched.
     */
    public function it_rejects_a_pending_trade(): void
    {
        Carbon::setTestNow('2026-03-21 12:00:00');

        /** @var Trade&MockInterface $trade */
        $trade = Mockery::mock(Trade::class)->makePartial();
        $trade->shouldReceive('save')->once();
        // Bypass the datetime cast that requires a DB connection.
        $trade->shouldReceive('setAttribute')
            ->with('completed_at', Mockery::type(Carbon::class))
            ->once()
            ->andReturnSelf();
        $trade->status = 'pending';
        $trade->to_user_id = 2;

        $actor = $this->makeUser(id: 2);

        $this->events->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(fn ($e) => $e instanceof TradeCompleted
                && $e->outcome === 'rejected'
                && $e->trade === $trade
                && $e->actor === $actor
            ));

        $result = $this->service->rejectTrade($trade, $actor);

        $this->assertSame($trade, $result);
        $this->assertSame('rejected', $result->status);

        Carbon::setTestNow();
    }

    /**
     * @test
     *
     * Rejecting a non-pending trade throws a ValidationException.
     */
    public function it_throws_when_rejecting_a_non_pending_trade(): void
    {
        $trade = $this->makeTradeMock(status: 'accepted', toUserId: 2);
        $trade->shouldNotReceive('save');
        $this->events->shouldNotReceive('dispatch');

        $this->expectException(ValidationException::class);

        $this->service->rejectTrade($trade, $this->makeUser(id: 2));
    }

    /**
     * @test
     *
     * Only the collectible owner (seller / to_user) may reject.
     */
    public function it_throws_when_non_owner_tries_to_reject(): void
    {
        $trade = $this->makeTradeMock(status: 'pending', toUserId: 2);
        $trade->shouldNotReceive('save');
        $this->events->shouldNotReceive('dispatch');

        $this->expectException(ValidationException::class);

        $this->service->rejectTrade($trade, $this->makeUser(id: 999));
    }

    // =====================================================================
    // cancelTrade()
    // =====================================================================

    /**
     * @test
     *
     * Buyer cancels a pending trade — status becomes "cancelled",
     * completed_at is set, and a TradeCompleted event is dispatched.
     */
    public function it_cancels_a_pending_trade(): void
    {
        Carbon::setTestNow('2026-03-21 12:00:00');

        /** @var Trade&MockInterface $trade */
        $trade = Mockery::mock(Trade::class)->makePartial();
        $trade->shouldReceive('save')->once();
        $trade->shouldReceive('setAttribute')
            ->with('completed_at', Mockery::type(Carbon::class))
            ->once()
            ->andReturnSelf();
        $trade->status = 'pending';
        $trade->from_user_id = 1;

        $actor = $this->makeUser(id: 1);

        $this->events->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(fn ($e) => $e instanceof TradeCompleted
                && $e->outcome === 'cancelled'
                && $e->trade === $trade
            ));

        $result = $this->service->cancelTrade($trade, $actor);

        $this->assertSame('cancelled', $result->status);

        Carbon::setTestNow();
    }

    /**
     * @test
     *
     * Cancelling a non-pending trade throws a ValidationException.
     */
    public function it_throws_when_cancelling_a_non_pending_trade(): void
    {
        $trade = $this->makeTradeMock(status: 'rejected', fromUserId: 1);
        $trade->shouldNotReceive('save');
        $this->events->shouldNotReceive('dispatch');

        $this->expectException(ValidationException::class);

        $this->service->cancelTrade($trade, $this->makeUser(id: 1));
    }

    /**
     * @test
     *
     * Only the trade initiator (buyer / from_user) may cancel.
     */
    public function it_throws_when_non_initiator_tries_to_cancel(): void
    {
        $trade = $this->makeTradeMock(status: 'pending', fromUserId: 1);
        $trade->shouldNotReceive('save');
        $this->events->shouldNotReceive('dispatch');

        $this->expectException(ValidationException::class);

        $this->service->cancelTrade($trade, $this->makeUser(id: 999));
    }

    // =====================================================================
    // acceptTrade() — guard-clause tests (no static calls needed)
    // =====================================================================

    /**
     * @test
     *
     * Accepting a non-pending trade throws before any DB work.
     */
    public function it_throws_when_accepting_a_non_pending_trade(): void
    {
        $trade = $this->makeTradeMock(status: 'accepted', toUserId: 2);
        $this->db->shouldNotReceive('transaction');
        $this->events->shouldNotReceive('dispatch');

        $this->expectException(ValidationException::class);

        $this->service->acceptTrade($trade, $this->makeUser(id: 2));
    }

    /**
     * @test
     *
     * Only the collectible owner (seller / to_user) may accept.
     */
    public function it_throws_when_non_owner_tries_to_accept(): void
    {
        $trade = $this->makeTradeMock(status: 'pending', toUserId: 2);
        $this->db->shouldNotReceive('transaction');
        $this->events->shouldNotReceive('dispatch');

        $this->expectException(ValidationException::class);

        $this->service->acceptTrade($trade, $this->makeUser(id: 999));
    }

    /**
     * @test
     *
     * After passing guard clauses, acceptTrade wraps its logic in
     * a database transaction.
     */
    public function it_wraps_accept_logic_in_a_transaction(): void
    {
        $trade = $this->makeTradeMock(
            status: 'pending',
            toUserId: 2,
            id: 100
        );

        $actor = $this->makeUser(id: 2);

        $this->db->shouldReceive('transaction')
            ->once()
            ->with(Mockery::type('callable'))
            ->andReturn($trade);

        $result = $this->service->acceptTrade($trade, $actor);

        $this->assertSame($trade, $result);
    }

    // =====================================================================
    // createOffer() — requires alias mocks (@runInSeparateProcess)
    // =====================================================================

    /**
     * @test
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     *
     * Successfully creates a trade offer: loads the collectible, looks up
     * the seller, validates balance, persists a Trade, and dispatches
     * a TradeCreated event.
     */
    public function it_creates_a_trade_offer_successfully(): void
    {
        $collectibleAlias = Mockery::mock('alias:Donk\\AigcCollectibles\\Model\\Collectible');
        $userAlias = Mockery::mock('alias:Flarum\\User\\User');

        // Use overload for Trade so both static calls AND instance save() work.
        $tradeOverload = Mockery::mock('overload:Donk\\AigcCollectibles\\Model\\Trade');
        $tradeOverload->shouldReceive('save')->once();

        $buyer = new User;
        $buyer->id = 1;

        $seller = new User;
        $seller->id = 2;

        $collectible = new \stdClass;
        $collectible->id = 10;
        $collectible->user_id = 2;

        $collectibleAlias->shouldReceive('query->where->where->firstOrFail')
            ->once()
            ->andReturn($collectible);

        $userAlias->shouldReceive('query->where->firstOrFail')
            ->once()
            ->andReturn($seller);

        $this->blindBoxService->shouldReceive('balanceOf')
            ->once()
            ->with($buyer)
            ->andReturn(5);

        // Trade::createOffer() returns an instance that passes the Trade type-hint.
        // The overload mock instance satisfies instanceof Trade.
        $tradeInstance = new Trade;
        $tradeOverload->shouldReceive('createOffer')
            ->once()
            ->with($buyer, $seller, $collectible, 3, 'I want this!')
            ->andReturn($tradeInstance);

        $this->events->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(fn ($e) => $e instanceof TradeCreated));

        $result = $this->service->createOffer($buyer, 10, 3, 'I want this!');

        $this->assertSame($tradeInstance, $result);
    }

    /**
     * @test
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     *
     * A user cannot offer a trade for their own collectible.
     */
    public function it_throws_when_buyer_trades_with_themselves(): void
    {
        $collectibleAlias = Mockery::mock('alias:Donk\\AigcCollectibles\\Model\\Collectible');
        $userAlias = Mockery::mock('alias:Flarum\\User\\User');

        $buyer = new User;
        $buyer->id = 1;

        $selfSeller = new User;
        $selfSeller->id = 1;

        $collectible = new \stdClass;
        $collectible->id = 10;
        $collectible->user_id = 1;

        $collectibleAlias->shouldReceive('query->where->where->firstOrFail')
            ->once()
            ->andReturn($collectible);

        $userAlias->shouldReceive('query->where->firstOrFail')
            ->once()
            ->andReturn($selfSeller);

        $this->blindBoxService->shouldNotReceive('balanceOf');
        $this->events->shouldNotReceive('dispatch');

        $this->expectException(ValidationException::class);

        $this->service->createOffer($buyer, 10, 3);
    }

    /**
     * @test
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     *
     * The buyer must have enough blind boxes to cover the offer.
     */
    public function it_throws_when_buyer_has_insufficient_blind_boxes(): void
    {
        $collectibleAlias = Mockery::mock('alias:Donk\\AigcCollectibles\\Model\\Collectible');
        $userAlias = Mockery::mock('alias:Flarum\\User\\User');

        $buyer = new User;
        $buyer->id = 1;

        $seller = new User;
        $seller->id = 2;

        $collectible = new \stdClass;
        $collectible->id = 10;
        $collectible->user_id = 2;

        $collectibleAlias->shouldReceive('query->where->where->firstOrFail')
            ->once()
            ->andReturn($collectible);

        $userAlias->shouldReceive('query->where->firstOrFail')
            ->once()
            ->andReturn($seller);

        $this->blindBoxService->shouldReceive('balanceOf')
            ->once()
            ->with($buyer)
            ->andReturn(2);

        $this->events->shouldNotReceive('dispatch');

        $this->expectException(ValidationException::class);

        $this->service->createOffer($buyer, 10, 5);
    }

    // =====================================================================
    // acceptTrade() — transaction body tests (requires alias mocks)
    //
    // NOTE: The full acceptTrade transaction closure uses Eloquent instance
    // methods (save()) on aliased model instances. Mockery alias mocks only
    // intercept static calls, not instance method calls, making it
    // impractical to unit test the complete flow. The guard clauses and
    // transaction-wrapping behavior are covered above. The ownership-changed
    // guard inside the transaction is tested below via alias mocks.
    // Full end-to-end coverage belongs in integration tests.
    // =====================================================================

    /**
     * @test
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     *
     * If the collectible was transferred to another user between the
     * trade creation and acceptance, the trade is rejected inside the
     * transaction.
     */
    public function it_throws_inside_transaction_when_collectible_ownership_changed(): void
    {
        $userAlias = Mockery::mock('alias:Flarum\\User\\User');
        $collectibleAlias = Mockery::mock('alias:Donk\\AigcCollectibles\\Model\\Collectible');

        Mockery::mock('alias:Donk\\AigcCollectibles\\Model\\Trade');

        $trade = new Trade;
        $trade->status = 'pending';
        $trade->to_user_id = 2;
        $trade->from_user_id = 1;
        $trade->collectible_id = 10;
        $trade->offered_boxes = 3;
        $trade->id = 100;

        $actor = new User;
        $actor->id = 2;

        $buyer = new User;
        $buyer->id = 1;

        $seller = new User;
        $seller->id = 2;

        // Collectible is now owned by someone else (user 99).
        $collectible = new Collectible;
        $collectible->id = 10;
        $collectible->user_id = 99;

        $userAlias->shouldReceive('query->where->lockForUpdate->firstOrFail')
            ->twice()
            ->andReturn($buyer, $seller);

        $collectibleAlias->shouldReceive('query->where->lockForUpdate->firstOrFail')
            ->once()
            ->andReturn($collectible);

        $this->blindBoxService->shouldNotReceive('transfer');
        $this->events->shouldNotReceive('dispatch');

        $this->db->shouldReceive('transaction')
            ->once()
            ->with(Mockery::type('callable'))
            ->andReturnUsing(fn (callable $cb) => $cb());

        $this->expectException(ValidationException::class);

        $this->service->acceptTrade($trade, $actor);
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    /**
     * Create a partial mock of {@see Trade} with save() stubbable
     * and key properties pre-set via Eloquent's attribute bag.
     *
     * @return Trade&MockInterface
     */
    protected function makeTradeMock(
        string $status = 'pending',
        int $fromUserId = 0,
        int $toUserId = 0,
        int $id = 1
    ): Trade {
        /** @var Trade&MockInterface $trade */
        $trade = Mockery::mock(Trade::class)->makePartial();
        $trade->status = $status;
        $trade->from_user_id = $fromUserId;
        $trade->to_user_id = $toUserId;
        $trade->id = $id;

        return $trade;
    }

    /**
     * Create a minimal {@see User} instance with the given ID.
     */
    protected function makeUser(int $id, int $blindBoxCount = 0): User
    {
        $user = new User;
        $user->id = $id;
        $user->blind_box_count = $blindBoxCount;

        return $user;
    }
}
