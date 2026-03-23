<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('blindboxes', function (Blueprint $table) {
    $table->increments('id');

    $table->unsignedInteger('user_id');

    $table->string('type', 40);
    $table->string('seed', 128);
    $table->string('status', 20)->default('unappraised');
    $table->unsignedInteger('budget')->nullable();

    $table->unsignedInteger('collectible_id')->nullable();

    $table->timestamps();

    $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
    $table->foreign('collectible_id')->references('id')->on('collectibles')->onDelete('set null');

    $table->index(['user_id', 'status']);
});
