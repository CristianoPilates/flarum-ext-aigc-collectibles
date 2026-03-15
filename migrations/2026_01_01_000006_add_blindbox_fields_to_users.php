<?php

use Flarum\Database\Migration;

return Migration::addColumns('users', [
    'blind_box_count' => ['integer', 'unsigned' => true, 'default' => 0],
    'showcase_collectible_id' => ['integer', 'unsigned' => true, 'nullable' => true],
    'last_checkin_at' => ['datetime', 'nullable' => true],
]);
