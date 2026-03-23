<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('blindbox_draw_rules', function (Blueprint $table) {
    $table->id();
    $table->string('blindbox_type', 40);
    $table->string('pool_category', 40);
    $table->boolean('required')->default(false);
    $table->timestamps();

    $table->unique(['blindbox_type', 'pool_category']);
});
