<?php

use Flarum\Database\Migration;

return Migration::addColumns('collectibles', [
    'name' => ['string', 'nullable' => true, 'length' => 100],
]);
