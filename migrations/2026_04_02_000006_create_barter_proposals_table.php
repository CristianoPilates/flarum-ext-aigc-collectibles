<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable(
    'barter_proposals',
    function (Blueprint $table) {
        $table->increments('id');
        $table->string('thread_type', 64);
        $table->unsignedInteger('thread_id');
        $table->unsignedInteger('proposer_user_id');
        $table->unsignedInteger('counterparty_user_id');
        $table->unsignedInteger('accepted_by_user_id')->nullable();
        $table->unsignedInteger('replaces_proposal_id')->nullable();
        $table->unsignedInteger('revision_number')->default(1);
        $table->enum('status', ['proposed', 'accepted', 'settling', 'completed', 'rejected', 'cancelled', 'superseded', 'failed'])
            ->default('proposed');
        $table->text('message')->nullable();
        $table->timestamp('completed_at')->nullable();
        $table->timestamps();

        $table->foreign('proposer_user_id')->references('id')->on('users')->onDelete('cascade');
        $table->foreign('counterparty_user_id')->references('id')->on('users')->onDelete('cascade');
        $table->foreign('accepted_by_user_id')->references('id')->on('users')->onDelete('set null');
        $table->foreign('replaces_proposal_id')->references('id')->on('barter_proposals')->onDelete('set null');

        $table->index(['thread_type', 'thread_id']);
        $table->index(['thread_type', 'thread_id', 'status']);
        $table->index('proposer_user_id');
        $table->index('counterparty_user_id');
        $table->unique(['thread_type', 'thread_id', 'revision_number'], 'barter_proposals_thread_revision_unique');
    }
);
