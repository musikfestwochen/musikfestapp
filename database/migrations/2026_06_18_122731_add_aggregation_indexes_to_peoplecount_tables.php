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
        Schema::table('peoplecount_interval_counts', function (Blueprint $table) {
            // Enforces one stored row per sensor interval; ingest code handles latest-wins upserts.
            $table->unique(['sensor_id', 'ts_from', 'ts_to'], 'pc_ic_sensor_from_to_unique');
        });

        Schema::table('peoplecount_assignments', function (Blueprint $table) {
            // Supports finding assignments active for an area/window chunk.
            $table->index(['area_id', 'active_from', 'active_to', 'sensor_id'], 'pc_assign_area_active_sensor_index');
        });

        Schema::table('peoplecount_area_aggregated_counts', function (Blueprint $table) {
            // Makes aggregate writes idempotent and speeds latest-count lookups.
            $table->unique(['area_id', 'period_start', 'period_end'], 'pc_aac_area_start_end_unique');
            $table->index(['area_id', 'period_end'], 'pc_aac_area_end_index');
        });

        Schema::table('peoplecount_area_single_resets', function (Blueprint $table) {
            // Supports reset lookup within an area's aggregation range.
            $table->index(['area_id', 'effective_at'], 'pc_single_resets_area_effective_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('peoplecount_area_single_resets', function (Blueprint $table) {
            $table->dropIndex('pc_single_resets_area_effective_index');
        });

        Schema::table('peoplecount_area_aggregated_counts', function (Blueprint $table) {
            $table->dropIndex('pc_aac_area_end_index');
            $table->dropUnique('pc_aac_area_start_end_unique');
        });

        Schema::table('peoplecount_assignments', function (Blueprint $table) {
            $table->dropIndex('pc_assign_area_active_sensor_index');
        });

        Schema::table('peoplecount_interval_counts', function (Blueprint $table) {
            $table->dropUnique('pc_ic_sensor_from_to_unique');
        });
    }
};
