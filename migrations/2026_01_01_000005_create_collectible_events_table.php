<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable(
    'collectible_events',
    function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('collectible_id');
        $table->enum('event_type', ['generated', 'traded', 'burned', 'minted']);
        $table->unsignedInteger('from_user_id')->nullable();
        $table->unsignedInteger('to_user_id')->nullable();
        $table->unsignedInteger('trade_id')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamp('created_at');

        $table->foreign('collectible_id')->references('id')->on('collectibles')->onDelete('cascade');
        $table->foreign('from_user_id')->references('id')->on('users')->onDelete('set null');
        $table->foreign('to_user_id')->references('id')->on('users')->onDelete('set null');
        $table->foreign('trade_id')->references('id')->on('trades')->onDelete('set null');

        $table->index('collectible_id');
        $table->index('event_type');
    }
);
