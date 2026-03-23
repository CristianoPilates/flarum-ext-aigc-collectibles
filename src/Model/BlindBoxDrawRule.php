<?php

namespace Donk\AigcCollectibles\Model;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;

/**
 * @property int $id
 * @property string $blindbox_type
 * @property string $pool_category
 * @property bool $required
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class BlindBoxDrawRule extends AbstractModel
{
    protected $table = 'blindbox_draw_rules';

    public $timestamps = true;

    protected $casts = [
        'required' => 'boolean',
    ];
}
