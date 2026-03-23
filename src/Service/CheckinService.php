<?php

namespace Donk\AigcCollectibles\Service;

use Carbon\Carbon;
use Donk\AigcCollectibles\Event\CheckedIn;
use Donk\AigcCollectibles\Model\BlindBox;
use Donk\AigcCollectibles\Model\CheckinRecord;
use Donk\AigcCollectibles\Service\Contracts\CheckinServiceInterface;
use Flarum\Foundation\ValidationException;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

class CheckinService implements CheckinServiceInterface
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly Dispatcher $events,
        private readonly SettingsRepositoryInterface $settings,
        // BlindBoxServiceInterface removed — creation uses Model factory directly
    ) {}

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

            for ($i = 0; $i < $rewardAmount; $i++) {
                BlindBox::createForUser($user, 'checkin_reward');
            }

            $this->db->table('users')
                ->where('id', $user->id)
                ->increment('blind_box_count', $rewardAmount);

            $user->last_checkin_at = Carbon::now();
            $user->save();

            $this->events->dispatch(new CheckedIn($user, $record, $rewardAmount));

            return $record;
        });
    }

    public function hasCheckedInToday(User $user): bool
    {
        return $user->last_checkin_at
            && Carbon::parse($user->last_checkin_at)->isToday();
    }
}
