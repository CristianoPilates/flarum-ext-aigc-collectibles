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
 * @property int $owner_id
 * @property string|null $ipfs_cid
 * @property string|null $metadata_cid
 * @property string|null $aigc_prompt
 * @property string $name
 * @property string $rarity
 * @property string $status
 * @property int|null $token_id
 * @property int $times_traded
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $owner
 * @property-read CollectibleEvent[] $events
 * @property-read Trade[] $trades
 */
class Collectible extends AbstractModel
{
    use HasStateMachine;
    use ScopeVisibilityTrait;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_GENERATING = 'generating';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_BURNED = 'burned';

    protected $table = 'collectibles';

    public $timestamps = true;

    protected $casts = [
        'times_traded' => 'integer',
        'token_id' => 'integer',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CollectibleEvent::class, 'collectible_id');
    }

    public function trades(): HasMany
    {
        return $this->hasMany(Trade::class, 'collectible_id');
    }

    public function getNameAttribute(): string
    {
        return 'Collectible #' . ($this->id ?: 'Draft');
    }

    public static function createDraft(int $ownerId, string $aigcPrompt, string $rarity): self
    {
        $collectible = new static;
        $collectible->owner_id = $ownerId;
        $collectible->aigc_prompt = $aigcPrompt;
        $collectible->rarity = $rarity;
        $collectible->status = self::STATUS_DRAFT;
        $collectible->times_traded = 0;
        $collectible->save();

        return $collectible;
    }
}
