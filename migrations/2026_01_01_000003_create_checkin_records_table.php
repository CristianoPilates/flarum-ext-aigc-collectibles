<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable(
    'checkin_records',
    function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('user_id');
        $table->unsignedInteger('reward_amount')->default(1);
        $table->timestamp('checked_in_at');

        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

        $table->index(['user_id', 'checked_in_at']);
    }
);
