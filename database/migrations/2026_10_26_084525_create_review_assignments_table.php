<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // OJS timestamps are UTC per OJS 3.6 source; verified from source, NOT against a live server. The app runs UTC, so values are stored as-is.
        Schema::create('review_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('ojs_id');
            $table->unsignedBigInteger('reviewer_id')->nullable();
            $table->unsignedInteger('round')->nullable();
            $table->unsignedBigInteger('review_round_id')->nullable();
            $table->unsignedSmallInteger('status')->nullable();
            $table->dateTime('date_assigned')->nullable();
            $table->dateTime('date_due')->nullable();
            $table->dateTime('date_response_due')->nullable();
            $table->dateTime('date_confirmed')->nullable();
            $table->dateTime('date_completed')->nullable();
            $table->boolean('declined')->default(false);
            $table->boolean('cancelled')->default(false);
            $table->timestamps();

            $table->unique('ojs_id');
            $table->index('date_due');
            $table->index('date_completed');

            // Declined/cancelled are low-cardinality with poor selectivity; a measured composite index remains a roadmap item.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_assignments');
    }
};
