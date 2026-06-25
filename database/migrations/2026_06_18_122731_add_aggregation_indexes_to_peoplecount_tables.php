<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // MariaDB/MySQL reuse the best available index (FK column as leftmost) for
        // a foreign key and drop the auto-created FK index as redundant. down()
        // therefore drops the foreign key before the index and re-adds it after, so
        // the original auto FK index is restored and up/down stays symmetrical.

        Schema::table('peoplecount_interval_counts', function (Blueprint $table) {
            // Enforces one stored row per sensor interval; ingest code handles latest-wins upserts.
            $table->unique(['sensor_id', 'ts_from', 'ts_to'], 'pc_ic_sensor_from_to_unique');
        });

        Schema::table('peoplecount_area_aggregated_counts', function (Blueprint $table) {
            // Makes aggregate writes idempotent and speeds latest-count lookups.
            $table->unique(['area_id', 'period_start', 'period_end'], 'pc_aac_area_start_end_unique');
            $table->index(['area_id', 'period_end'], 'pc_aac_area_end_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('peoplecount_area_aggregated_counts', function (Blueprint $table) {
            $table->dropIndex('pc_aac_area_end_index');
            $table->dropForeign(['area_id']);
            $table->dropUnique('pc_aac_area_start_end_unique');
            $table->foreign('area_id')->references('id')->on('peoplecount_areas')->cascadeOnDelete();
        });

        Schema::table('peoplecount_interval_counts', function (Blueprint $table) {
            $table->dropForeign(['sensor_id']);
            $table->dropUnique('pc_ic_sensor_from_to_unique');
            $table->foreign('sensor_id')->references('id')->on('peoplecount_sensors');
        });
    }
};
