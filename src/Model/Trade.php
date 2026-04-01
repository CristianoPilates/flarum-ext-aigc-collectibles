<?php

namespace Donk\AigcCollectibles\Model;

use Carbon\Carbon;
use Donk\AigcCollectibles\StateMachine\HasStateMachine;
use Flarum\Database\AbstractModel;
use Flarum\Database\ScopeVisibilityTrait;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $from_user_id
 * @property int $to_user_id
 * @property int $collectible_id
 * @property int $offered_boxes
 * @property string $status
 * @property string|null $note
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $fromUser
 * @property-read User $toUser
 * @property-read Collectible $collectible
 */
class Trade extends AbstractModel
{
    use HasStateMachine;
    use ScopeVisibilityTrait;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_SETTLING = 'settling';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    protected $table = 'trades';

    public $timestamps = true;

    protected $casts = [
        'offered_boxes' => 'integer',
        'completed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, self>
     */
    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    /**
     * @return BelongsTo<User, self>
     */
    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    /**
     * @return BelongsTo<Collectible, self>
     */
    public function collectible(): BelongsTo
    {
        return $this->belongsTo(Collectible::class, 'collectible_id');
    }

    public static function createOffer(User $buyer, User $seller, Collectible $collectible, int $offeredBoxes, ?string $note = null): self
    {
        $trade = new static();
        $trade->from_user_id = $buyer->id;
        $trade->to_user_id = $seller->id;
        $trade->collectible_id = $collectible->id;
        $trade->offered_boxes = $offeredBoxes;
        $trade->status = self::STATUS_PENDING;
        $trade->note = $note;

        return $trade;
    }

    public function markCompletedAt(): void
    {
        $this->completed_at = Carbon::now();
    }
}
