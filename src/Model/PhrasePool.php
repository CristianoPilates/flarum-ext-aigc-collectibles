<?php

namespace Donk\AigcCollectibles\Model;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;

/**
 * @property int $id
 * @property string $category
 * @property string $phrase
 * @property int $cost
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PhrasePool extends AbstractModel
{
    protected $table = 'phrase_pools';

    public $timestamps = true;
}
