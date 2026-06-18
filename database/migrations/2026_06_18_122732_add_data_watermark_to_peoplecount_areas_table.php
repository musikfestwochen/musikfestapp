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
            // Tracks newest interval data included in aggregation so late arrivals can trigger recalculation.
            $table->dateTime('data_watermark')
                ->nullable()
                ->comment('Newest interval received_at included in area aggregation; used to detect late-arriving data.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('peoplecount_areas', function (Blueprint $table) {
            $table->dropColumn('data_watermark');
        });
    }
};
