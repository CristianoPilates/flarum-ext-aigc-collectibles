<?php

namespace Donk\AigcCollectibles\Model;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $collectible_id
 * @property string $event_type
 * @property int|null $from_user_id
 * @property int|null $to_user_id
 * @property int|null $trade_id
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property-read Collectible $collectible
 * @property-read User|null $fromUser
 * @property-read User|null $toUser
 */
class CollectibleEvent extends AbstractModel
{
    protected $table = 'collectible_events';

    public $timestamps = false;

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Collectible, self>
     */
    public function collectible(): BelongsTo
    {
        return $this->belongsTo(Collectible::class, 'collectible_id');
    }

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

    public static function log(
        Collectible $collectible,
        string $eventType,
        ?int $fromUserId = null,
        ?int $toUserId = null,
        ?int $tradeId = null,
        ?array $metadata = null
    ): self {
        $event = new static();
        $event->collectible_id = $collectible->id;
        $event->event_type = $eventType;
        $event->from_user_id = $fromUserId;
        $event->to_user_id = $toUserId;
        $event->trade_id = $tradeId;
        $event->metadata = $metadata;
        $event->created_at = Carbon::now();

        return $event;
    }
}
