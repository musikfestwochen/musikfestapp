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
        Schema::table('peoplecount_areas', function (Blueprint $table) {
            $table->integer('occupancy_alert_threshold')->nullable()->after('name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('eastereggs_activated')->default(true)->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('peoplecount_areas', function (Blueprint $table) {
            if (Schema::hasColumn('peoplecount_areas', 'occupancy_alert_threshold')) {
                $table->dropColumn('occupancy_alert_threshold');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'eastereggs_activated')) {
                $table->dropColumn('eastereggs_activated');
            }
        });
    }
};
