<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable(
    'barter_proposal_items',
    function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('proposal_id');
        $table->unsignedInteger('owner_user_id');
        $table->enum('asset_type', ['collectible', 'blind_box']);
        $table->unsignedInteger('asset_id');
        $table->unsignedInteger('position')->default(0);
        $table->json('snapshot')->nullable();
        $table->timestamps();

        $table->foreign('proposal_id')->references('id')->on('barter_proposals')->onDelete('cascade');
        $table->foreign('owner_user_id')->references('id')->on('users')->onDelete('cascade');

        $table->index('proposal_id');
        $table->index('owner_user_id');
        $table->index(['asset_type', 'asset_id']);
        $table->unique(['proposal_id', 'asset_type', 'asset_id'], 'barter_proposal_items_asset_unique');
    }
);
