<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // OJS timestamps are UTC per OJS 3.6 source; verified from source, NOT against a live server. The app runs UTC, so values are stored as-is.
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('context_id');
            $table->unsignedBigInteger('ojs_id');
            $table->unsignedSmallInteger('status');
            $table->unsignedTinyInteger('stage_id');
            $table->string('submission_progress')->nullable();
            $table->unsignedBigInteger('current_publication_id')->nullable();
            $table->dateTime('date_submitted')->nullable();
            $table->dateTime('date_last_activity')->nullable();
            $table->dateTime('last_modified')->nullable();
            $table->timestamps();

            $table->unique(['context_id', 'ojs_id']);
            $table->index('status');
            $table->index('stage_id');
            $table->index('date_last_activity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
