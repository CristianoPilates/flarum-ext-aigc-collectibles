<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable(
    'collectibles',
    function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('user_id')->comment('Current owner');
        $table->unsignedInteger('original_user_id')->comment('Original creator');
        $table->string('name', 255);
        $table->string('ipfs_cid', 100)->nullable();
        $table->string('metadata_cid', 100)->nullable();
        $table->text('aigc_prompt')->nullable();
        $table->enum('rarity', ['common', 'rare', 'epic', 'legendary'])->default('common');
        $table->enum('status', ['generating', 'completed', 'failed', 'burned'])->default('generating');
        $table->unsignedBigInteger('token_id')->nullable();
        $table->json('generation_params')->nullable();
        $table->unsignedInteger('times_traded')->default(0);
        $table->timestamps();

        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        $table->foreign('original_user_id')->references('id')->on('users')->onDelete('cascade');

        $table->index('user_id');
        $table->index('original_user_id');
        $table->index('rarity');
        $table->index('status');
    }
);
