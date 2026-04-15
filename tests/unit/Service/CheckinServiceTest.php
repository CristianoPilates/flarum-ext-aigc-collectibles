<?php

namespace Donk\AigcCollectibles\Tests\unit\Service;

use Donk\AigcCollectibles\Model\CheckinRecord;
use Donk\AigcCollectibles\Service\CheckinService;
use Donk\AigcCollectibles\Service\Contracts\BlindBoxServiceInterface;
use Illuminate\Support\Carbon;
use Flarum\Foundation\ValidationException;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\unit\TestCase;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;

class CheckinServiceTest extends TestCase
{
    /** @var SettingsRepositoryInterface|MockInterface */
    protected $settings;

    /** @var ConnectionInterface|MockInterface */
    protected $db;

    /** @var Dispatcher|MockInterface */
    protected $events;

    /** @var BlindBoxServiceInterface|MockInterface */
    protected $blindBoxService;

    protected CheckinService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = Mockery::mock(SettingsRepositoryInterface::class);
        $this->db = Mockery::mock(ConnectionInterface::class);
        $this->events = Mockery::mock(Dispatcher::class);
        $this->blindBoxService = Mockery::mock(BlindBoxServiceInterface::class);

        $this->service = new CheckinService(
            $this->db,
            $this->events,
            $this->settings,
            $this->blindBoxService
        );
    }

    #[Test]
    public function it_throws_validation_exception_when_user_has_already_checked_in_today(): void
    {
        $user = $this->makeUser(id: 101);

        /** @var CheckinService&MockInterface $service */
        $service = Mockery::mock(CheckinService::class, [
            $this->db,
            $this->events,
            $this->settings,
            $this->blindBoxService,
        ])->makePartial();

        $service->shouldReceive('hasCheckedInToday')
            ->once()
            ->with($user)
            ->andReturnTrue();

        $this->settings->shouldNotReceive('get');
        $this->db->shouldNotReceive('transaction');
        $this->events->shouldNotReceive('dispatch');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('You have already checked in today.');

        $service->performCheckin($user);
    }

    #[Test]
    public function it_reads_reward_from_settings_and_returns_the_transaction_result(): void
    {
        $user = $this->makeUser(id: 202);
        $expectedRecord = new CheckinRecord;

        /** @var CheckinService&MockInterface $service */
        $service = Mockery::mock(CheckinService::class, [
            $this->db,
            $this->events,
            $this->settings,
            $this->blindBoxService,
        ])->makePartial();

        $service->shouldReceive('hasCheckedInToday')
            ->once()
            ->with($user)
            ->andReturnFalse();

        $this->settings->shouldReceive('get')
            ->once()
            ->with('donk-aigc-collectibles.checkin-reward', 1)
            ->andReturn(3);

        // 这里不执行闭包，只验证服务会把业务逻辑包裹在事务中。
        $this->db->shouldReceive('transaction')
            ->once()
            ->with(Mockery::type('callable'))
            ->andReturn($expectedRecord);

        $this->events->shouldNotReceive('dispatch');

        $actualRecord = $service->performCheckin($user);

        $this->assertSame($expectedRecord, $actualRecord);
    }

    #[Test]
    public function it_checks_today_status_via_the_users_last_checkin_timestamp(): void
    {
        $userA = $this->makeUser(id: 1);
        $userB = $this->makeUser(id: 2);
        $userC = $this->makeUser(id: 3);

        $userA->last_checkin_at = Carbon::now()->subHour();
        $userB->last_checkin_at = Carbon::now()->subDay();
        $userC->last_checkin_at = null;

        $this->assertTrue($this->service->hasCheckedInToday($userA));
        $this->assertFalse($this->service->hasCheckedInToday($userB));
        $this->assertFalse($this->service->hasCheckedInToday($userC));
    }

    protected function makeUser(int $id): User
    {
        $user = new User;
        $user->id = $id;

        return $user;
    }
}
