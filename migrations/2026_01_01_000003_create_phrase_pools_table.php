<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('phrase_pools', function (Blueprint $table) {
    $table->id();
    $table->string('category', 40);
    $table->string('phrase');
    $table->unsignedInteger('cost');
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->index(['category', 'is_active', 'cost']);
});
