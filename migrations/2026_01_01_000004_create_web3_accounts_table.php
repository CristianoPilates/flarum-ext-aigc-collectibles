<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable(
    'web3_accounts',
    function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('user_id');
        $table->string('address', 80)->unique();
        $table->string('source', 50)->default('metamask');
        $table->string('type', 20)->default('evm');
        $table->timestamp('attached_at')->nullable();
        $table->timestamp('last_verified_at')->nullable();

        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

        $table->index('user_id');
    }
);
