<?php

namespace Donk\AigcCollectibles\Model;

use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $proposal_id
 * @property int $owner_user_id
 * @property string $asset_type
 * @property int $asset_id
 * @property int $position
 * @property array<string, mixed>|null $snapshot
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read BarterProposal $proposal
 * @property-read User $ownerUser
 */
class BarterProposalItem extends AbstractModel
{
    protected $table = 'barter_proposal_items';

    public $timestamps = true;

    protected $casts = [
        'proposal_id' => 'integer',
        'owner_user_id' => 'integer',
        'asset_id' => 'integer',
        'position' => 'integer',
        'snapshot' => 'array',
    ];

    /**
     * @return BelongsTo<BarterProposal, self>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(BarterProposal::class, 'proposal_id');
    }

    /**
     * @return BelongsTo<User, self>
     */
    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
