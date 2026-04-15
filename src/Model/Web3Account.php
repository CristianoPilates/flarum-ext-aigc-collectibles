<?php

namespace Donk\AigcCollectibles\Model;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\Database\ScopeVisibilityTrait;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $address
 * @property string $source
 * @property string $type
 * @property Carbon|null $attached_at
 * @property Carbon|null $last_verified_at
 * @property-read User $user
 */
class Web3Account extends AbstractModel
{
    use ScopeVisibilityTrait;

    protected $table = 'web3_accounts';

    public $timestamps = true;

    const CREATED_AT = 'attached_at';
    const UPDATED_AT = 'last_verified_at';

    /**
     * @return BelongsTo<User, self>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function create(User $user, string $address, string $source = 'metamask', string $type = 'evm'): self
    {
        $account = new self();
        $account->user_id = $user->id;
        $account->address = strtolower($address);
        $account->source = $source;
        $account->type = $type;

        return $account;
    }
}
