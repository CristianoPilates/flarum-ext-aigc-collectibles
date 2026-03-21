<?php

namespace Donk\AigcCollectibles\Tests\unit\Service;

use Donk\AigcCollectibles\Service\BlindBoxService;
use Flarum\Foundation\ValidationException;
use Flarum\Testing\unit\TestCase;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;
use Mockery;
use Mockery\MockInterface;

class BlindBoxServiceTest extends TestCase
{
    /** @var ConnectionInterface|MockInterface */
    protected $db;

    protected BlindBoxService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Mockery::mock(ConnectionInterface::class);
        $this->service = new BlindBoxService($this->db);
    }

    /** @test */
    public function it_returns_the_users_blind_box_balance(): void
    {
        $user = $this->makeUser(id: 1, blindBoxCount: 7);

        $this->assertSame(7, $this->service->balanceOf($user));
    }

    /** @test */
    public function it_awards_blind_boxes_successfully(): void
    {
        $user = $this->makeUser(id: 10, blindBoxCount: 3);
        $builder = Mockery::mock();

        $this->db->shouldReceive('table')
            ->once()
            ->with('users')
            ->andReturn($builder);

        $builder->shouldReceive('where')
            ->once()
            ->with('id', 10)
            ->andReturnSelf();

        $builder->shouldReceive('increment')
            ->once()
            ->with('blind_box_count', 2)
            ->andReturn(1);

        $this->service->award($user, 2);

        $this->assertSame(5, $user->blind_box_count);
    }

    /** @test */
    public function it_rejects_invalid_award_amount(): void
    {
        $user = $this->makeUser(id: 10, blindBoxCount: 3);
        $this->db->shouldNotReceive('table');

        $this->expectException(ValidationException::class);

        $this->service->award($user, 0);
    }

    /** @test */
    public function it_throws_when_award_update_fails(): void
    {
        $user = $this->makeUser(id: 10, blindBoxCount: 3);
        $builder = Mockery::mock();

        $this->db->shouldReceive('table')
            ->once()
            ->with('users')
            ->andReturn($builder);

        $builder->shouldReceive('where')
            ->once()
            ->with('id', 10)
            ->andReturnSelf();

        $builder->shouldReceive('increment')
            ->once()
            ->with('blind_box_count', 2)
            ->andReturn(0);

        $this->expectException(ValidationException::class);

        $this->service->award($user, 2);
    }

    /** @test */
    public function it_spends_blind_boxes_successfully(): void
    {
        $user = $this->makeUser(id: 12, blindBoxCount: 5);
        $builder = Mockery::mock();

        $this->db->shouldReceive('table')
            ->once()
            ->with('users')
            ->andReturn($builder);

        $builder->shouldReceive('where')
            ->once()
            ->with('id', 12)
            ->andReturnSelf();

        $builder->shouldReceive('where')
            ->once()
            ->with('blind_box_count', '>=', 2)
            ->andReturnSelf();

        $builder->shouldReceive('decrement')
            ->once()
            ->with('blind_box_count', 2)
            ->andReturn(1);

        $this->service->spend($user, 2);

        $this->assertSame(3, $user->blind_box_count);
    }

    /** @test */
    public function it_rejects_invalid_spend_amount(): void
    {
        $user = $this->makeUser(id: 12, blindBoxCount: 5);
        $this->db->shouldNotReceive('table');

        $this->expectException(ValidationException::class);

        $this->service->spend($user, -1);
    }

    /** @test */
    public function it_throws_when_balance_is_insufficient_to_spend(): void
    {
        $user = $this->makeUser(12, 1);
        $builder = Mockery::mock();

        $this->db->shouldReceive('table')
            ->once()
            ->with('users')
            ->andReturn($builder);

        $builder->shouldReceive('where')
            ->once()
            ->with('id', 12)
            ->andReturnSelf();

        $builder->shouldReceive('where')
            ->once()
            ->with('blind_box_count', '>=', 2)
            ->andReturnSelf();

        $builder->shouldReceive('decrement')
            ->once()
            ->with('blind_box_count', 2)
            ->andReturn(0);

        $this->expectException(ValidationException::class);

        $this->service->spend($user, 2);
    }

    /** @test */
    public function it_transfers_by_spending_then_awarding(): void
    {
        $from = $this->makeUser(id: 1, blindBoxCount: 10);
        $to = $this->makeUser(id: 2, blindBoxCount: 0);

        /** @var BlindBoxService&MockInterface $service */
        $service = Mockery::mock(BlindBoxService::class, [$this->db])->makePartial();

        $service->shouldReceive('spend')
            ->once()
            ->with($from, 3)
            ->ordered();

        $service->shouldReceive('award')
            ->once()
            ->with($to, 3)
            ->ordered();

        $service->transfer($from, $to, 3);
    }

    /** @test */
    public function it_rejects_invalid_transfer_amount(): void
    {
        $from = $this->makeUser(id: 1, blindBoxCount: 10);
        $to = $this->makeUser(id: 2, blindBoxCount: 0);

        /** @var BlindBoxService&MockInterface $service */
        $service = Mockery::mock(BlindBoxService::class, [$this->db])->makePartial();
        $service->shouldNotReceive('spend');
        $service->shouldNotReceive('award');

        $this->expectException(ValidationException::class);

        $service->transfer($from, $to, 0);
    }

    protected function makeUser(int $id, int $blindBoxCount): User
    {
        $user = new User;
        $user->id = $id;
        $user->blind_box_count = $blindBoxCount;

        return $user;
    }
}
