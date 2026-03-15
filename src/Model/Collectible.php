<?php

namespace Donk\AigcCollectibles\Model;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\Database\ScopeVisibilityTrait;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property int $original_user_id
 * @property string $name
 * @property string|null $ipfs_cid
 * @property string|null $metadata_cid
 * @property string|null $aigc_prompt
 * @property string $rarity
 * @property string $status
 * @property int|null $token_id
 * @property array|null $generation_params
 * @property int $times_traded
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read User $originalUser
 * @property-read CollectibleEvent[] $events
 * @property-read Trade[] $trades
 */
class Collectible extends AbstractModel
{
    use ScopeVisibilityTrait;

    protected $table = 'collectibles';

    public $timestamps = true;

    protected $casts = [
        'generation_params' => 'array',
        'times_traded' => 'integer',
        'token_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function originalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'original_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CollectibleEvent::class, 'collectible_id');
    }

    public function trades(): HasMany
    {
        return $this->hasMany(Trade::class, 'collectible_id');
    }

    public static function createForUser(User $user, string $rarity, string $name): self
    {
        $collectible = new static();
        $collectible->user_id = $user->id;
        $collectible->original_user_id = $user->id;
        $collectible->name = $name;
        $collectible->rarity = $rarity;
        $collectible->status = 'generating';
        $collectible->times_traded = 0;

        return $collectible;
    }
}
