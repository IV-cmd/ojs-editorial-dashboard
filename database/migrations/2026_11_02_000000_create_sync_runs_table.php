<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per ojs:sync attempt: the audit trail behind the dashboard's
        // "last sync" line and the source of the Prometheus pipeline metrics.
        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status', 16)->index();
            $table->timestamp('started_at', 3);
            $table->timestamp('finished_at', 3)->nullable();
            $table->unsignedInteger('submissions_listed')->default(0);
            $table->unsignedInteger('submissions_upserted')->default(0);
            $table->unsignedInteger('review_assignments_upserted')->default(0);
            $table->unsignedInteger('failures_api_error')->default(0);
            $table->unsignedInteger('failures_validation')->default(0);
            $table->unsignedInteger('failures_db')->default(0);
            $table->unsignedInteger('failures_other')->default(0);
            $table->string('error_excerpt', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_runs');
    }
};
