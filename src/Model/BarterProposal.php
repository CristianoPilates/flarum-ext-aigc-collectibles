<?php

namespace Donk\AigcCollectibles\Model;

use Carbon\Carbon;
use Donk\AigcCollectibles\StateMachine\HasStateMachine;
use Flarum\Database\AbstractModel;
use Flarum\Database\ScopeVisibilityTrait;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $thread_type
 * @property int $thread_id
 * @property int $proposer_user_id
 * @property int $counterparty_user_id
 * @property int|null $accepted_by_user_id
 * @property int|null $replaces_proposal_id
 * @property int $revision_number
 * @property string $status
 * @property string|null $message
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $proposer
 * @property-read User $counterparty
 * @property-read User|null $acceptedBy
 * @property-read self|null $replacesProposal
 * @property-read \Illuminate\Database\Eloquent\Collection<int, BarterProposalItem> $items
 */
class BarterProposal extends AbstractModel
{
    use HasStateMachine;
    use ScopeVisibilityTrait;

    public const THREAD_DIALOG = 'dialog';

    public const STATUS_PROPOSED = 'proposed';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_SETTLING = 'settling';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_SUPERSEDED = 'superseded';
    public const STATUS_FAILED = 'failed';

    public const ASSET_COLLECTIBLE = 'collectible';
    public const ASSET_BLIND_BOX = 'blind_box';

    protected $table = 'barter_proposals';

    public $timestamps = true;

    protected $casts = [
        'thread_id' => 'integer',
        'proposer_user_id' => 'integer',
        'counterparty_user_id' => 'integer',
        'accepted_by_user_id' => 'integer',
        'replaces_proposal_id' => 'integer',
        'revision_number' => 'integer',
        'completed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, self>
     */
    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposer_user_id');
    }

    /**
     * @return BelongsTo<User, self>
     */
    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counterparty_user_id');
    }

    /**
     * @return BelongsTo<User, self>
     */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    /**
     * @return BelongsTo<self, self>
     */
    public function replacesProposal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_proposal_id');
    }

    /**
     * @return HasMany<BarterProposalItem, self>
     */
    public function items(): HasMany
    {
        return $this->hasMany(BarterProposalItem::class, 'proposal_id')->orderBy('position');
    }

    public function involvesUser(int $userId): bool
    {
        return $this->proposer_user_id === $userId || $this->counterparty_user_id === $userId;
    }

    public function otherParticipantId(int $userId): ?int
    {
        if ($this->proposer_user_id === $userId) {
            return $this->counterparty_user_id;
        }

        if ($this->counterparty_user_id === $userId) {
            return $this->proposer_user_id;
        }

        return null;
    }

    public function markCompletedAt(): void
    {
        $this->completed_at = Carbon::now();
    }
}
