<?php

namespace Donk\AigcCollectibles\Model;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $reward_amount
 * @property Carbon $checked_in_at
 * @property-read User $user
 */
class CheckinRecord extends AbstractModel
{
    protected $table = 'checkin_records';

    public $timestamps = false;

    protected $casts = [
        'reward_amount' => 'integer',
        'checked_in_at' => 'datetime',
    ];

    public int $id;

    public int $user_id;

    public int $reward_amount;

    /**
     * @var Carbon\Carbon
     */
    public Carbon $checked_in_at;

    /**
     * @var Flarum\User\User
     */
    public User $user;

    /**
     * @return BelongsTo<User,CheckinRecord>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function create(User $user, int $rewardAmount): self
    {
        $record = new static;
        $record->user_id = $user->id;
        $record->reward_amount = $rewardAmount;
        $record->checked_in_at = Carbon::now();

        return $record;
    }
}
