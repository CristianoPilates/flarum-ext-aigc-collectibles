<?php

namespace Donk\AigcCollectibles\Service;

use Carbon\Carbon;
use Donk\AigcCollectibles\Event\CheckedIn;
use Donk\AigcCollectibles\Model\CheckinRecord;
use Flarum\Foundation\ValidationException;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

class CheckinService
{
    protected SettingsRepositoryInterface $settings;
    protected BlindBoxService $blindBoxService;
    protected ConnectionInterface $db;
    protected Dispatcher $events;

    public function __construct(
        SettingsRepositoryInterface $settings,
        BlindBoxService $blindBoxService,
        ConnectionInterface $db,
        Dispatcher $events
    ) {
        $this->settings = $settings;
        $this->blindBoxService = $blindBoxService;
        $this->db = $db;
        $this->events = $events;
    }

    public function performCheckin(User $user): CheckinRecord
    {
        if ($this->hasCheckedInToday($user)) {
            throw new ValidationException([
                'checkin' => 'You have already checked in today.',
            ]);
        }

        $rewardAmount = (int) $this->settings->get('donk-aigc-collectibles.checkin-reward', 1);

        return $this->db->transaction(function () use ($user, $rewardAmount) {
            $record = CheckinRecord::create($user, $rewardAmount);
            $record->save();

            $this->blindBoxService->award($user, $rewardAmount);

            $user->last_checkin_at = Carbon::now();
            $user->save();

            $this->events->dispatch(new CheckedIn($user, $record, $rewardAmount));

            return $record;
        });
    }

    public function hasCheckedInToday(User $user): bool
    {
        $today = Carbon::today();

        return CheckinRecord::query()
            ->where('user_id', $user->id)
            ->whereDate('checked_in_at', $today)
            ->exists();
    }
}
