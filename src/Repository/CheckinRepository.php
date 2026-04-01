<?php

namespace Donk\AigcCollectibles\Repository;

use Carbon\Carbon;
use Donk\AigcCollectibles\Model\CheckinRecord;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CheckinRepository
{
    /**
     * @return Builder<CheckinRecord>
     */
    public function query(): Builder
    {
        return CheckinRecord::query();
    }

    /**
     * @return Collection<int, CheckinRecord>
     */
    public function findByUser(User $user, int $limit = 30): Collection
    {
        return $this->query()
            ->where('user_id', $user->id)
            ->orderBy('checked_in_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function hasCheckedInToday(User $user): bool
    {
        return $this->query()
            ->where('user_id', $user->id)
            ->whereDate('checked_in_at', Carbon::today())
            ->exists();
    }

    public function getConsecutiveDays(User $user): int
    {
        $records = $this->query()
            ->where('user_id', $user->id)
            ->orderBy('checked_in_at', 'desc')
            ->limit(365)
            ->get();

        $days = 0;
        $expectedDate = Carbon::today();

        foreach ($records as $record) {
            $checkinDate = Carbon::parse($record->checked_in_at)->startOfDay();

            if ($checkinDate->equalTo($expectedDate)) {
                $days++;
                $expectedDate = $expectedDate->subDay();
            } elseif ($checkinDate->lessThan($expectedDate)) {
                break;
            }
        }

        return $days;
    }
}
