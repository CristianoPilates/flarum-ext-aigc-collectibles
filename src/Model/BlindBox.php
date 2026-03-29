<?php

namespace Donk\AigcCollectibles\Model;

use Carbon\Carbon;
use Donk\AigcCollectibles\StateMachine\HasStateMachine;
use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $seed
 * @property string $status
 * @property int|null $budget
 * @property int|null $collectible_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class BlindBox extends AbstractModel
{
    use HasStateMachine;

    protected $table = 'blindboxes';

    public $timestamps = true;

    const STATUS_UNAPPRAISED = 'unappraised';

    const STATUS_APPRAISED = 'appraised';

    const STATUS_OPENED = 'opened';

    public static function createForUser(User $user, string $type): static
    {
        $box = new static;
        $box->user_id = $user->id;
        $box->type = $type;
        $box->seed = bin2hex(random_bytes(32));
        $box->status = self::STATUS_UNAPPRAISED;
        $box->save();

        return $box;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function collectible(): BelongsTo
    {
        return $this->belongsTo(Collectible::class);
    }
}
