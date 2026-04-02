<?php

namespace Donk\AigcCollectibles\Tests\unit\Service;

use Donk\AigcCollectibles\Service\BlindBoxService;
use Flarum\Foundation\ValidationException;
use Flarum\Testing\unit\TestCase;
use Flarum\User\User;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;

class BlindBoxServiceTest extends TestCase
{
    /** @var ConnectionInterface|MockInterface */
    protected $db;

    /** @var Dispatcher|MockInterface */
    protected $events;

    /** @var BusDispatcher|MockInterface */
    protected $bus;

    protected BlindBoxService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Mockery::mock(ConnectionInterface::class);
        $this->events = Mockery::mock(Dispatcher::class);
        $this->bus = Mockery::mock(BusDispatcher::class);

        $this->service = new BlindBoxService(
            $this->db,
            $this->events,
            Mockery::mock(\SM\Factory\FactoryInterface::class),
            $this->bus,
        );
    }

    #[Test]
    public function it_returns_the_users_blind_box_balance(): void
    {
        $user = $this->makeUser(id: 1, blindBoxCount: 7);

        $this->db->shouldReceive('table')
            ->once()
            ->with('users')
            ->andReturn($builder = Mockery::mock());

        $builder->shouldReceive('where')
            ->once()
            ->with('id', 1)
            ->andReturnSelf();

        $builder->shouldReceive('value')
            ->once()
            ->with('blind_box_count')
            ->andReturn(7);

        $this->assertSame(7, $this->service->balanceOf($user));
    }

    #[Test]
    public function it_transfers_blind_boxes_between_users(): void
    {
        $from = $this->makeUser(id: 1, blindBoxCount: 10);
        $to = $this->makeUser(id: 2, blindBoxCount: 0);
        $selectBuilder = Mockery::mock();
        $ownershipBuilder = Mockery::mock();
        $moveBuilder = Mockery::mock();
        $fromCountBuilder = Mockery::mock();
        $fromUserBuilder = Mockery::mock();
        $toCountBuilder = Mockery::mock();
        $toUserBuilder = Mockery::mock();

        $this->db->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(fn (callable $callback) => $callback());

        $this->db->shouldReceive('table')
            ->times(5)
            ->with('blindboxes')
            ->andReturn($selectBuilder, $ownershipBuilder, $moveBuilder, $fromCountBuilder, $toCountBuilder);

        $this->db->shouldReceive('table')
            ->twice()
            ->with('users')
            ->andReturn($fromUserBuilder, $toUserBuilder);

        $selectBuilder->shouldReceive('where')
            ->once()
            ->with('user_id', 1)
            ->andReturnSelf();

        $selectBuilder->shouldReceive('whereIn')
            ->once()
            ->with('status', ['unappraised', 'appraised'])
            ->andReturnSelf();

        $selectBuilder->shouldReceive('orderBy')
            ->once()
            ->with('id')
            ->andReturnSelf();

        $selectBuilder->shouldReceive('limit')
            ->once()
            ->with(3)
            ->andReturnSelf();

        $selectBuilder->shouldReceive('lockForUpdate')
            ->once()
            ->andReturnSelf();

        $selectBuilder->shouldReceive('pluck')
            ->once()
            ->with('id')
            ->andReturn(new Collection([11, 12, 13]));

        $moveBuilder->shouldReceive('whereIn')
            ->once()
            ->with('id', [11, 12, 13])
            ->andReturnSelf();

        $ownershipBuilder->shouldReceive('where')
            ->once()
            ->with('user_id', 1)
            ->andReturnSelf();

        $ownershipBuilder->shouldReceive('whereIn')
            ->once()
            ->with('status', ['unappraised', 'appraised'])
            ->andReturnSelf();

        $ownershipBuilder->shouldReceive('whereIn')
            ->once()
            ->with('id', [11, 12, 13])
            ->andReturnSelf();

        $ownershipBuilder->shouldReceive('lockForUpdate')
            ->once()
            ->andReturnSelf();

        $ownershipBuilder->shouldReceive('pluck')
            ->once()
            ->with('id')
            ->andReturn(new Collection([11, 12, 13]));

        $moveBuilder->shouldReceive('update')
            ->once()
            ->with(Mockery::on(fn (array $payload) => $payload['user_id'] === 2 && isset($payload['updated_at'])))
            ->andReturn(3);

        $fromCountBuilder->shouldReceive('where')
            ->once()
            ->with('user_id', 1)
            ->andReturnSelf();

        $fromCountBuilder->shouldReceive('whereIn')
            ->once()
            ->with('status', ['unappraised', 'appraised'])
            ->andReturnSelf();

        $fromCountBuilder->shouldReceive('count')
            ->once()
            ->andReturn(7);

        $fromUserBuilder->shouldReceive('where')
            ->once()
            ->with('id', 1)
            ->andReturnSelf();

        $fromUserBuilder->shouldReceive('update')
            ->once()
            ->with(['blind_box_count' => 7])
            ->andReturn(1);

        $toCountBuilder->shouldReceive('where')
            ->once()
            ->with('user_id', 2)
            ->andReturnSelf();

        $toCountBuilder->shouldReceive('whereIn')
            ->once()
            ->with('status', ['unappraised', 'appraised'])
            ->andReturnSelf();

        $toCountBuilder->shouldReceive('count')
            ->once()
            ->andReturn(3);

        $toUserBuilder->shouldReceive('where')
            ->once()
            ->with('id', 2)
            ->andReturnSelf();

        $toUserBuilder->shouldReceive('update')
            ->once()
            ->with(['blind_box_count' => 3])
            ->andReturn(1);

        $this->service->transfer($from, $to, 3);
    }

    #[Test]
    public function it_rejects_invalid_transfer_amount(): void
    {
        $from = $this->makeUser(id: 1, blindBoxCount: 10);
        $to = $this->makeUser(id: 2, blindBoxCount: 0);

        $this->db->shouldNotReceive('table');

        $this->expectException(ValidationException::class);

        $this->service->transfer($from, $to, 0);
    }

    #[Test]
    public function it_throws_when_the_sender_cannot_cover_the_transfer(): void
    {
        $from = $this->makeUser(id: 1, blindBoxCount: 1);
        $to = $this->makeUser(id: 2, blindBoxCount: 0);

        $this->db->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn (callable $callback) => $callback());

        $this->db->shouldReceive('table')
            ->once()
            ->with('blindboxes')
            ->andReturn($builder = Mockery::mock());

        $builder->shouldReceive('where')
            ->once()
            ->with('user_id', 1)
            ->andReturnSelf();

        $builder->shouldReceive('whereIn')
            ->once()
            ->with('status', ['unappraised', 'appraised'])
            ->andReturnSelf();

        $builder->shouldReceive('orderBy')
            ->once()
            ->with('id')
            ->andReturnSelf();

        $builder->shouldReceive('limit')
            ->once()
            ->with(2)
            ->andReturnSelf();

        $builder->shouldReceive('lockForUpdate')
            ->once()
            ->andReturnSelf();

        $builder->shouldReceive('pluck')
            ->once()
            ->with('id')
            ->andReturn(new Collection([11]));

        $this->expectException(ValidationException::class);

        $this->service->transfer($from, $to, 2);
    }

    #[Test]
    public function it_returns_early_when_transfer_specific_receives_no_box_ids(): void
    {
        $from = $this->makeUser(id: 1, blindBoxCount: 3);
        $to = $this->makeUser(id: 2, blindBoxCount: 4);

        $this->db->shouldNotReceive('transaction');
        $this->db->shouldNotReceive('table');

        $this->service->transferSpecific($from, $to, []);
    }

    #[Test]
    public function it_throws_when_transfer_specific_detects_unavailable_boxes(): void
    {
        $from = $this->makeUser(id: 1, blindBoxCount: 10);
        $to = $this->makeUser(id: 2, blindBoxCount: 0);

        $this->db->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn (callable $callback) => $callback());

        $this->db->shouldReceive('table')
            ->once()
            ->with('blindboxes')
            ->andReturn($ownershipBuilder = Mockery::mock());

        $ownershipBuilder->shouldReceive('where')
            ->once()
            ->with('user_id', 1)
            ->andReturnSelf();

        $ownershipBuilder->shouldReceive('whereIn')
            ->once()
            ->with('status', ['unappraised', 'appraised'])
            ->andReturnSelf();

        $ownershipBuilder->shouldReceive('whereIn')
            ->once()
            ->with('id', [11, 12, 13])
            ->andReturnSelf();

        $ownershipBuilder->shouldReceive('lockForUpdate')
            ->once()
            ->andReturnSelf();

        $ownershipBuilder->shouldReceive('pluck')
            ->once()
            ->with('id')
            ->andReturn(new Collection([11, 12]));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('One or more blind boxes are unavailable for transfer.');

        $this->service->transferSpecific($from, $to, [11, 12, 13]);
    }

    #[Test]
    public function it_maps_leading_zero_counts_to_budget_tiers(): void
    {
        $this->assertSame(20, $this->invokeHiddenMethod('zerosToBudget', [4]));
        $this->assertSame(40, $this->invokeHiddenMethod('zerosToBudget', [5]));
        $this->assertSame(80, $this->invokeHiddenMethod('zerosToBudget', [6]));
        $this->assertSame(160, $this->invokeHiddenMethod('zerosToBudget', [7]));
    }

    #[Test]
    public function it_maps_budget_values_to_rarity_tiers(): void
    {
        $this->assertSame('common', $this->invokeHiddenMethod('budgetToRarity', [20]));
        $this->assertSame('rare', $this->invokeHiddenMethod('budgetToRarity', [40]));
        $this->assertSame('epic', $this->invokeHiddenMethod('budgetToRarity', [80]));
        $this->assertSame('legendary', $this->invokeHiddenMethod('budgetToRarity', [160]));
    }

    protected function makeUser(int $id, int $blindBoxCount): User
    {
        $user = new User;
        $user->id = $id;
        $user->blind_box_count = $blindBoxCount;

        return $user;
    }

    private function invokeHiddenMethod(string $method, array $args): mixed
    {
        $invoker = \Closure::bind(
            function (array $args) use ($method) {
                return $this->{$method}(...$args);
            },
            $this->service,
            BlindBoxService::class
        );

        return $invoker($args);
    }
}
