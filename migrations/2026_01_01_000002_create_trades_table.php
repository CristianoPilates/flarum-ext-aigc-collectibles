<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable(
    'trades',
    function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('from_user_id')->comment('Buyer offering blind boxes');
        $table->unsignedInteger('to_user_id')->comment('Seller who owns the collectible');
        $table->unsignedInteger('collectible_id');
        $table->unsignedInteger('offered_boxes');
        $table->enum('status', ['pending', 'accepted', 'rejected', 'cancelled'])->default('pending');
        $table->string('note', 500)->nullable();
        $table->timestamp('completed_at')->nullable();
        $table->timestamps();

        $table->foreign('from_user_id')->references('id')->on('users')->onDelete('cascade');
        $table->foreign('to_user_id')->references('id')->on('users')->onDelete('cascade');
        $table->foreign('collectible_id')->references('id')->on('collectibles')->onDelete('cascade');

        $table->index('from_user_id');
        $table->index('to_user_id');
        $table->index('collectible_id');
        $table->index('status');
    }
);
