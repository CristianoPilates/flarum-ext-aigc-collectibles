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

    /* 去数据库里读取这些字段时, Laravel 会先根据cast自动做数据类型转换, 再返回 */
    protected $casts = [
        'generation_params' => 'array',
        'times_traded' => 'integer',
        'token_id' => 'integer',
    ];

    /*
 * @return BelongsTo<User,Collectible> 多对一, 多个藏品可以BelongsTo 一个用户. $user = $collectible->user; // 拿到所属用户 */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User,Collectible>
     */
    public function originalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'original_user_id');
    }

    /*
 * @return HasMany<CollectibleEvent,Collectible> 藏品有多种"生命周期日志" */
    public function events(): HasMany
    {
        return $this->hasMany(CollectibleEvent::class, 'collectible_id');
    }

    /*
 * @return HasMany<Trade,Collectible> 一个 Collectible可以有很多条 Trade */
    public function trades(): HasMany
    {
        return $this->hasMany(Trade::class, 'collectible_id');
    }

    public static function createForUser(User $user, string $rarity, string $name): self
    {
        $collectible = new static;
        $collectible->user_id = $user->id;
        $collectible->original_user_id = $user->id;
        $collectible->name = $name;
        $collectible->rarity = $rarity;
        $collectible->status = 'generating';
        $collectible->times_traded = 0;

        return $collectible;
    }
}
