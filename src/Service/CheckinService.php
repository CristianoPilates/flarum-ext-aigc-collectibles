<?php

namespace Donk\AigcCollectibles\Service;

use Carbon\Carbon;
use Donk\AigcCollectibles\Event\CheckedIn;
use Donk\AigcCollectibles\Model\CheckinRecord;
use Donk\AigcCollectibles\Service\Contracts\BlindBoxServiceInterface;
use Donk\AigcCollectibles\Service\Contracts\CheckinServiceInterface;
use Flarum\Foundation\ValidationException;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

class CheckinService implements CheckinServiceInterface
{
    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected BlindBoxServiceInterface $blindBoxService,
        protected ConnectionInterface $db,
        protected Dispatcher $events
    ) {}

    /**
     * Performs a daily check-in for the given user and grants the configured reward.
     *
     * Validates that the user has not already checked in today, then creates and saves
     * the check-in record, awards blind boxes, updates the user's last check-in time,
     * and dispatches the check-in event within a single database transaction.
     *
     * @param  User  $user  The user performing the check-in.
     * @return CheckinRecord The persisted check-in record.
     *
     * @throws ValidationException If the user has already checked in today.
     */
    public function performCheckin(User $user): CheckinRecord
    {
        if ($this->hasCheckedInToday($user)) {
            throw new ValidationException([
                'checkin' => 'You have already checked in today.',
            ]);
        }

        $rewardAmount = (int) $this->settings->get('donk-aigc-collectibles.checkin-reward', 1);

        // Model是数据实体，不是服务. 永远直接 new / ::create() / ::find()，不要走容器
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

    /**
     * Determine whether the given user has checked in today.
     *
     * This checks for an existing check-in record for the user
     * where the check-in date matches the current date.
     *
     * @param  User  $user  The user to evaluate.
     * @return bool True if a check-in exists for today; otherwise false.
     */
    public function hasCheckedInToday(User $user): bool
    {
        $today = Carbon::today();

        return CheckinRecord::query()
            ->where('user_id', $user->id)
            ->whereDate('checked_in_at', $today)
            ->exists();
    }
}
