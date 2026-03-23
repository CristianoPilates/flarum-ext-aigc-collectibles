<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('collectibles', function (Blueprint $table) {
    $table->increments('id');

    $table->unsignedInteger('owner_id')->comment('Current owner');

    $table->string('ipfs_cid', 100)->nullable();
    $table->string('metadata_cid', 100)->nullable();
    $table->text('aigc_prompt')->nullable();

    $table->string('rarity', 20)->default('common');
    $table->string('status', 20)->default('draft');

    $table->unsignedBigInteger('token_id')->nullable();
    $table->unsignedInteger('times_traded')->default(0);

    $table->timestamps();

    $table->foreign('owner_id')->references('id')->on('users')->onDelete('cascade');

    $table->index('owner_id');
    $table->index('rarity');
    $table->index('status');
});
